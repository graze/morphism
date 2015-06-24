<?php

namespace Graze\Morphism\Console\Command;

use Graze\Morphism\Configuration\ConfigurationParser;
use Graze\Morphism\Connection\ConnectionResolver;
use Graze\Morphism\Console\Output\OutputHelper;
use Graze\Morphism\Diff\ConfirmableDiffApplier;
use Graze\Morphism\Diff\DiffApplier;
use Graze\Morphism\Diff\Differ;
use Graze\Morphism\Diff\DifferConfiguration;
use Graze\Morphism\ExtractorFactory;
use Graze\Morphism\Listener\LogListener;
use Graze\Morphism\Parse\PathParser;
use Graze\Morphism\Parse\TokenStreamFactory;
use Graze\Morphism\Specification\TableSpecification;
use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class DiffCommand extends Command
{
    /**
     * @var EventDispatcherInterface
     */
    private $dispatcher;

    /**
     * @param EventDispatcherInterface $dispatcher
     */
    public function __construct(EventDispatcherInterface $dispatcher)
    {
        parent::__construct();
        $this->dispatcher = $dispatcher;
    }

    protected function configure()
    {
        $this->setName('diff')
            ->setDescription('Extracts schema definitions from the named connections, and outputs the necessary statements to transform them into what is defined under the schema path.')
            ->addArgument(
                'config-file',
                InputArgument::REQUIRED,
                'A YAML file mapping connection names to parameters. See README for details.'
            )
            ->addArgument(
                'connection',
                InputArgument::OPTIONAL,
                'The connection name to perform the diff on (optional, defaults to all)'
            )
            ->addOption(
                'engine',
                null,
                InputOption::VALUE_REQUIRED,
                'Set the default database engine',
                'InnoDB'
            )
            ->addOption(
                'collation',
                null,
                InputOption::VALUE_OPTIONAL,
                'Set the default collation'
            )
            ->addOption(
                'no-create-table',
                null,
                InputOption::VALUE_NONE,
                'Do not output CREATE TABLE statements'
            )
            ->addOption(
                'no-drop-table',
                null,
                InputOption::VALUE_NONE,
                'Do not output DROP TABLE statements'
            )
            ->addOption(
                'no-alter-engine',
                null,
                InputOption::VALUE_NONE,
                'Do not output ALTER TABLE ... ENGINE=...'
            )
            ->addOption(
                'schema-path',
                null,
                InputOption::VALUE_REQUIRED,
                'Location of schemas',
                './schema'
            )
            ->addOption(
                'apply-changes',
                null,
                InputOption::VALUE_REQUIRED,
                'Apply changes? (yes/no/confirm)',
                'no'
            )
            ->addOption(
                'log-dir',
                null,
                InputOption::VALUE_REQUIRED,
                'Log applied changes for each connection to a file in the given directory'
            )
            ->addOption(
                'no-log-skipped',
                null,
                InputOption::VALUE_NONE,
                'Do not log skipped queries (commented out)'
            );
    }

    /**
     * {@inheritDoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $outputHelper = new OutputHelper($output);
        $applyChanges = $input->getOption('apply-changes');

        // setup the config
        $parser = new ConfigurationParser();
        $config = $parser->parse($input->getArgument('config-file'));

        // figure out which connection names we're using
        $connectionNames = $config->getConnectionNames();
        if ($input->getArgument('connection')) {
            $connectionNames = [$input->getArgument('connection')];
        }

        // build the differ
        $differConfig = DifferConfiguration::buildFromInput($input);
        $streamFactory = new TokenStreamFactory(new ExtractorFactory(), new Filesystem());
        $pathParser = new PathParser();
        $differ = new Differ($differConfig, $streamFactory, $pathParser);

        // setup listeners
        if ($differConfig->getLogDir()) {
            $listener = new LogListener(new Filesystem(), $differConfig->getLogDir());
            $this->dispatcher->addListener('query.applied', [$listener, 'onQueryApplied']);
            $this->dispatcher->addListener('query.skipped', [$listener, 'onQuerySkipped']);
        }

        // diff for each connection
        $connectionResolver = new ConnectionResolver($config);
        foreach ($connectionNames as $connectionName) {
            $outputHelper->title('Connection: ' . $connectionName);

            $connection = $connectionResolver->resolveFromName($connectionName);
            $entry = $config->getEntry($connectionName);
            $diff = $differ->diff(
                $connection,
                new TableSpecification($entry['morphism']['include'], $entry['morphism']['exclude'])
            );

            if ($diff) {
                foreach ($diff->getQueries() as $query) {
                    $outputHelper->sql($query);
                }

                // apply the diff to the connection if there is one
                if ($applyChanges !== 'no') {
                    if ($applyChanges === 'confirm') {
                        $output->writeln('');
                        $output->writeln('<comment>-- Confirm changes to ' . $connection->getDatabase() . ':</comment>');

                        $applier = new ConfirmableDiffApplier($this->dispatcher, $input, $output,
                            $this->getHelper('question'));
                        $applier->apply($diff, $connection);
                    } else {
                        $applier = new DiffApplier($this->dispatcher);
                        $applier->apply($diff, $connection);
                    }
                }
            }
        }
    }
}
