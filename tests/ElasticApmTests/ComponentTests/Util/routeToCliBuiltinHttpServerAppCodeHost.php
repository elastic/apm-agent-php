<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\ComponentTests\Util;

use Elastic\Apm\ElasticApm;

require __DIR__ . '/../../../bootstrap.php';
require __DIR__ . '/defineTopLevelCodeIdGlobalVar.php';

/** @noinspection PhpUnhandledExceptionInspection */
BuiltinHttpServerAppCodeHost::run(/* ref */ $globalTopLevelCodeId);

if (!is_null($globalTopLevelCodeId)) {
    if ($globalTopLevelCodeId === TopLevelCodeId::SPAN_BEGIN_END) {
        $span = ElasticApm::getCurrentTransaction()->beginCurrentSpan(
            'top_level_code_span_name',
            'top_level_code_span_type'
        );
        $span->setLabel('top_level_code_span_end_file_name', __FILE__);
        $span->setLabel('top_level_code_span_end_line_number', __LINE__ + 1);
        $span->end();
    }
}
