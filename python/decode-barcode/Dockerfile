FROM python:3.12-slim
WORKDIR /usr/src/app

COPY requirements.txt ./
COPY main.py ./
COPY gunicorn.conf.py ./
COPY utils ./utils
RUN pip install --upgrade pip && \
pip install --no-cache-dir -r requirements.txt

EXPOSE 80

CMD ["gunicorn"]