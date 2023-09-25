# crash_collector.sh

## Introduction

crash_collector.sh is a nash script designed to analyze core dump files and create an `tar.xz` archive containing essential diagnostic data. This data includes the core dump file, shared libraries (.so files) loaded into crashed process, and the executable program that crashed. These data are necessary to diagnose the core dump in a different environment.

## Prerequisites

To use the Core Dump Analyzer Script, ensure you have the required dependencies installed on your system. These dependencies include:

   - `awk`
   - `eu-readelf`
   - `file`
   - `grep`
   - `ldd`
   - `readelf`
   - `sed`
   - `tar`

## Usage

```bash
Usage:  
    ./crash_collector.sh -c CORE_FILE [-f EXE_PATH ] [ -o OUTPUT_PATH ]  
    
    Arguments description:  
        -c core_file            required, core dump file to analyse  
        -e executable_path      executable path, optional if file is installed  
        -o ouput_path           path where core dump archive should be created  
        -h                      print this help  
        -v                      verbose mode  
```

### Examples

#### Analyze a core dump file, let the script deduce the executable path, and specify the output directory
```bash
./crash_collector.sh -c /path/to/core.dump -o /path/to/output_directory
```

#### Analyze a core dump file and provide the path to the executable that crashed
```bash
./crash_collector.sh -c /path/to/core.dump -e /path/to/executable -o /path/to/output_directory
```
