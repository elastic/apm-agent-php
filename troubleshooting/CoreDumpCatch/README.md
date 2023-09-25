# core_dump_catch.sh

## Introduction

core_dump_catch.sh is a simple bash script designed to enhance core dump handling on Linux systems. By configuring the script as part of the core_pattern, you can specify where core dump files should be stored, allowing for more flexibility and control in managing core dumps. This documentation will guide you through the setup and usage of core_dump_catch.sh, with a focus on customizing the storage path to meet your requirements. This tool will store core dump, crashed executable and log files.

## Prerequisites

Before using the Core Dump Capture Script, ensure the following prerequisites are met:

- Linux operating system (tested on Ubuntu 20.04 and CentOS 7)
-  Bash shell (usually available by default)
-  Sufficient disk space to store core dump files
-  Permission to execute the script and access core dump files
- You need to adjust core limits to ensure the script works properly. Set the core limits to "unlimited" to allow for the capture of core file to be captured in its entirety
```bash
ulimit -c unlimited
```
- Edit linux /proc/sys/kernel/core_pattern and put there string like this.
```bash
|/path/to/core_dump_catch.sh %p %P %i %s %h %t %E %e
```
 Make it temporary or permanent, as appropriate.
- Edit core_dump_catch.sh script and change `OUTPUT_DIRECTORY` to location where you want to store core data.

## Testing

To test it, simply open bash and type following code. It will crash sleep command and script should store core dump files in `OUTPUT_DIRECTORY` location.

```bash
sleep 10 &
kill -SIGSEGV $!
```
