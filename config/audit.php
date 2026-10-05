<?php

return [
    // How long report change history (test_report_audits) is kept. Labs usually must keep
    // result amendment history for years, so the default is 5 years.
    'retention_days' => (int) env('AUDIT_RETENTION_DAYS', 1825),
];
