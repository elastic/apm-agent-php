<?php

declare(strict_types=1);

namespace ElasticApmTests\ComponentTests\Util;

use Elastic\Apm\ElasticApm;
use Elastic\Apm\Impl\GlobalTracerHolder;

require __DIR__ . '/../../../bootstrap.php';
require __DIR__ . '/defineTopLevelCodeIdGlobalVar.php';

BuiltinHttpServerAppCodeHost::run(/* ref */ $globalTopLevelCodeId);

if (!is_null($globalTopLevelCodeId)) {
    AppCodeHostBase::setAgentEphemeralId();
    if ($globalTopLevelCodeId === TopLevelCodeId::SPAN_BEGIN_END) {
        $span = ElasticApm::getCurrentTransaction()->beginCurrentSpan(
            'top_level_code_span_name',
            'top_level_code_span_type'
        );
        $span->context()->setLabel('top_level_code_span_end_file_name', __FILE__);
        $span->context()->setLabel('top_level_code_span_end_line_number', __LINE__ + 1);
        $span->end();
    }
}
