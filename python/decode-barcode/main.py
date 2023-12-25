import io

from flask import Flask, request, jsonify
from utils.decode_barcode import parse_barcode

app = Flask(__name__)


@app.route('/')
def index():
    return jsonify({
        'status': 'success'
    })


@app.route('/decode-barcode', methods=['POST'])
def decode_barcode():
    file = request.files['image']
    file_data = file.stream.read()
    io_file = io.BytesIO(file_data)
    response = parse_barcode(io_file)
    return jsonify(response)


if __name__ == '__main__':
    app.run('127.0.0.1', 80, debug=False)
