<?php

declare(strict_types=1);

namespace Eccube2\Migration\Command;

trait DatabaseConnectionTrait
{
    /**
     * Get database connection and type
     *
     * @return array{0: \SC_Query, 1: string}
     */
    protected function getConnection(): array
    {
        return [\SC_Query::getSingletonInstance(), DB_TYPE];
    }
}
