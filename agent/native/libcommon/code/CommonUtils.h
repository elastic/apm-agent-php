
#pragma once

#include <chrono>
#include <string>

namespace elasticapm::utils {

[[maybe_unused]] bool blockSignal(int signo);

std::chrono::milliseconds convertDurationWithUnit(std::string timeWithUnit); // default unit - ms, handles ms, s, m, throws std::invalid_argument if unit is unknown

std::string getParameterizedString(std::string_view format);

}