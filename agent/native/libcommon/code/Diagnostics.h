
#pragma once

#include "PhpBridgeInterface.h"

namespace elasticapm::utils {

void storeDiagnosticInformation(std::string_view outputFileName, elasticapm::php::PhpBridgeInterface const &bridge); //throws

}
