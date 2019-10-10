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
    'http://nginx:8000/?page_id=2',
    'http://nginx:8000/?page_id=16',
    'http://nginx:8000/?p=6',
    'http://nginx:8000/?p=8',
    'http://nginx:8000/?p=10#comments',
    'http://nginx:8000/?p=12',
    'http://nginx:8000/?p=14',
    'http://nginx:8000/?author=1',
    'http://nginx:8000/?cat=1'

]

while True:
    url = random.choice(endpoints)
    headers = {'content-type': 'application/json', 'Accept-Charset': 'UTF-8'}
    r = requests.post(url, headers=headers)
    logging.info("[%d] %s" % (r.status_code, url))
