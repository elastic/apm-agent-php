#!/usr/bin/python

import sys
import requests
import random
import logging
import time

from queue import Queue
from threading import Thread

root = logging.getLogger()
root.setLevel(logging.DEBUG)

handler = logging.StreamHandler(sys.stdout)
handler.setLevel(logging.INFO)
formatter = logging.Formatter('%(asctime)s - %(name)s - %(levelname)s - %(message)s')
handler.setFormatter(formatter)
root.addHandler(handler)

_timeout = 0.50
_n_threads = 4
if len(sys.argv) == 2:
    _n_threads = int(sys.argv[1])

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
    'http://nginx:8000/?cat=1',
]

def do_request(q, wid):
    while True:
        if q.empty() == False:
            url = q.get()
            headers = {'content-type': 'application/json', 'Accept-Charset': 'UTF-8'}

            # Add randomly a dist. tracing header
            if random.randint(0, 10) == wid:
                a = random.randint(1000, 9999)
                b = random.randint(1000, 9999)
                headers['elastic-apm-traceparent'] = "00-%da6be83a31fb66a455cbb74ab%d-%dfae6bd7c%d-01" % (a, b, b, a)

            # Do Request
            r = requests.get(url, headers=headers)
            logging.info("[%d] worker %d - %s" % (r.status_code, wid, url))

        time.sleep(_timeout)

if __name__ == '__main__':
    q = Queue()

    for i in range(_n_threads):
        worker = Thread(target=do_request, args=(q, i))
        worker.setDaemon(True)
        worker.start()

    while True:
        url = random.choice(endpoints)
        q.put(url)

    q.join()