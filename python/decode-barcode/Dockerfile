FROM python:3.12
WORKDIR /usr/src/app

COPY requirements.txt ./
RUN pip install --upgrade pip && \
pip install --no-cache-dir -r requirements.txt && \
apt-get update && \
apt-get install -y python3-opencv
COPY . .

EXPOSE 80

CMD ["gunicorn", "-b 0.0.0.0:80", "-w 4", "main:app"]