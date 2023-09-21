
#include "CommonUtils.h"
#include <algorithm>
#include <chrono>
#include <string_view>
#include <signal.h>
#include <stddef.h>
#include <sys/types.h>
#include <unistd.h>


namespace elasticapm::utils {

using namespace std::string_view_literals;


[[maybe_unused]] bool blockSignal(int signo) {
    sigset_t currentSigset;

    if (pthread_sigmask(SIG_BLOCK, NULL, &currentSigset) != 0) {
    	sigemptyset(&currentSigset);
    }

    if (sigismember(&currentSigset, signo) == 1) {
        return true;
    }

    sigaddset(&currentSigset, signo);
    return pthread_sigmask(SIG_BLOCK, &currentSigset, NULL) == 0;
}


std::chrono::milliseconds convertDurationWithUnit(std::string timeWithUnit) {

    auto endWithoutSpaces = std::remove_if(timeWithUnit.begin(), timeWithUnit.end(), [](unsigned char c) { return std::isspace(c); });
    timeWithUnit.erase(endWithoutSpaces, timeWithUnit.end());

    double timeValue = std::stod(timeWithUnit.data());
    auto unitPos = timeWithUnit.find_first_not_of("0123456789."sv);

    if (unitPos == std::string_view::npos) {
        return std::chrono::duration_cast<std::chrono::milliseconds>(std::chrono::duration<double, std::milli>(timeValue)) ;
    }

    std::string unit{timeWithUnit.substr(unitPos)};

    if (unit == "ms") {
        return std::chrono::duration_cast<std::chrono::milliseconds>(std::chrono::duration<double, std::milli>(timeValue)) ;
    } else if (unit == "s") {
        return std::chrono::duration_cast<std::chrono::milliseconds>(std::chrono::duration<double>(timeValue)) ;
    } else if (unit == "m") {
       return std::chrono::duration_cast<std::chrono::milliseconds>(std::chrono::duration<double, std::ratio<60>>(timeValue)) ;
    }

    throw std::invalid_argument("Invalid time unit.");
}

std::string getParameterizedString(std::string_view format) {

    std::string out;

    for (auto c = format.begin(); c < format.end(); ++c) {
        if (*c == '%') {
            c++;
            if (c == format.end()) {
                out.append(1, '%');
                break;
            }

            switch (*c) {
                case 'p':
                    out.append(std::to_string(getpid()));
                    break;
                case 't':
                    out.append(std::to_string(std::chrono::milliseconds(std::time(NULL)).count()));
                    break;
                default:
                    out.append(1, '%');
                    out.append(1, *c);
                }
        } else {
            out.append({*c});
        }
    }

    return out;
}


}