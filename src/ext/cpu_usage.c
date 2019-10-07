#include "stdlib.h"
#include "stdio.h"
#include "string.h"
#include <php.h>
#include "cpu_usage.h"

void read_cpu(unsigned long long *user, unsigned long long *user_low, unsigned long long *sys, unsigned long long *idle){
    FILE* file = fopen("/proc/stat", "r");
    int n = fscanf(file, "cpu %llu %llu %llu %llu", user, user_low, sys, idle);
    fclose(file);
}

void read_cpu_process(int pid, unsigned long long *user, unsigned long long *user_low, unsigned long long *sys, unsigned long long *idle){
    char *proc_file = emalloc(sizeof(char) * 80);
    sprintf(proc_file, "/proc/%d/stat", pid);

    FILE* file = fopen(proc_file, "r");
    int n = fscanf(file, "cpu %llu %llu %llu %llu", user, user_low, sys, idle);
    fclose(file);
    efree(proc_file);
}

double get_cpu_usage(unsigned long long last_user, unsigned long long last_user_low, unsigned long long last_sys, unsigned long long last_idle){
    unsigned long long user, user_low, sys, idle;

    read_cpu(&user, &user_low, &sys, &idle);

    return make_cpu_percent(last_user, last_user_low, last_sys, last_idle, user, user_low, sys, idle);
}

double get_cpu_process_usage(int pid, unsigned long long last_user, unsigned long long last_user_low, unsigned long long last_sys, unsigned long long last_idle){
    unsigned long long user, user_low, sys, idle;

    read_cpu_process(pid, &user, &user_low, &sys, &idle);

    return make_cpu_percent(last_user, last_user_low, last_sys, last_idle, user, user_low, sys, idle);
}

double make_cpu_percent(unsigned long long last_user, unsigned long long last_user_low, unsigned long long last_sys, unsigned long long last_idle, unsigned long long user, unsigned long long user_low, unsigned long long sys, unsigned long long idle){
    unsigned long long total;
    double percent;

    if (user < last_user || user_low < last_user_low || sys < last_sys || idle < last_idle){
        //Overflow detection. Just skip this value.
        return -1.0;
    }
    total = (user - last_user) + (user_low - last_user_low) + (sys - last_sys);
    percent = total;
    total += (idle - last_idle);
    return percent / total;
}
