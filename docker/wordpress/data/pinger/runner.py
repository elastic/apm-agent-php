#!/usr/bin/python

import sys
import requests
import random
import logging

root = logging.getLogger()
root.setLevel(logging.DEBUG)

handler = logging.StreamHandler(sys.stdout)
handler.setLevel(logging.INFO)
formatter = logging.Formatter('%(asctime)s - %(name)s - %(levelname)s - %(message)s')
handler.setFormatter(formatter)
root.addHandler(handler)

endpoints = [
    'http://nginx:8000/',
]

while True:
    url = random.choice(endpoints)
    headers = {'content-type': 'application/json', 'Accept-Charset': 'UTF-8'}
    r = requests.post(url, headers=headers)
    logging.info("[%d] %s" % (r.status_code, url))
