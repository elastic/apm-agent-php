import logging
import os
import sys

#Detecting venv

if sys.prefix == sys.base_prefix:
    logging.error("venv not detected")
    exit(1)
else:
    logging.info("Virtual environment detected: " + sys.prefix)


exit(0)