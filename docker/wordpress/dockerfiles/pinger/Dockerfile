FROM python:3.7.2-alpine3.9

RUN apk update

WORKDIR /app

COPY ./data/pinger /app
RUN pip install --upgrade pip
RUN pip install -r requirements.txt
RUN chmod +x *.py

#CMD /bin/sh -c /app/run.sh

ENTRYPOINT [ "/bin/sh" ]