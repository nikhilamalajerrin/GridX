<?php

namespace GridX\Support\Reporting\Contracts;

use GridX\Support\Reporting\ReportSchemaRegistry;

interface ReportSchema
{
    /**
     * Register tables and columns for report generation.
     */
    public function registerReportSchema(ReportSchemaRegistry $registry): void;
}
