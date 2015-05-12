<?php

namespace Graze\Morphism\Diff;

use Doctrine\DBAL\Connection;

class DiffApplier
{
    /**
     * @param Diff $diff
     * @param Connection $connection
     *
     * @throws \Doctrine\DBAL\DBALException
     */
    public function apply(Diff $diff, Connection $connection)
    {
        $logHandle = null;
//        $logDir = $diffConfig->getLogDir();

//        if ($logDir !== null) {
//            $logFile = "{$logDir}/{$connection->getDatabase()}.sql";
//            $logHandle = fopen($logFile, 'w');
//            if ($logHandle === false) {
//                fprintf(STDERR, "Could not open log file for writing: $logFile\n");
//                exit(1);
//            }
//        }

        foreach ($diff->getQueries() as $query) {
            if ($this->shouldApply($query)) {
//                if ($logHandle) {
//                    fwrite($logHandle, "$query;\n\n");
//                }
                $connection->executeQuery($query);
            }
//            elseif ($logHandle && $diffConfig->isLogSkipped()) {
//                fwrite($logHandle,
//                    "-- [SKIPPED]\n" .
//                    preg_replace('/^/xms', '-- ', $query) .  ";\n" .
//                    "\n"
//                );
//            }
        }
    }

    /**
     * @param string $query
     *
     * @return bool
     */
    protected function shouldApply($query)
    {
        return true;
    }
}
