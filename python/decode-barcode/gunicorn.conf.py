import multiprocessing

bind = "0.0.0.0:80"
workers = multiprocessing.cpu_count()
errorlog = '-'
accesslog = '-'
syslog = True
syslog_prefix = 'Decode-Barcode'
wsgi_app = 'main:app'
