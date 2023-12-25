import io

from flask import Flask, request, jsonify
from utils.decode_barcode import parse_barcode

app = Flask(__name__)
app.config['MAX_CONTENT_LENGTH'] = 1024 * 1024 * 16


@app.route('/')
def index():
    return jsonify({
        'status': 'success'
    })


@app.route('/decode-barcode', methods=['POST'])
def decode_barcode():
    file_data = request.get_data()
    io_file = io.BytesIO(file_data)
    response = parse_barcode(io_file)
    return jsonify(response)


if __name__ == '__main__':
    app.run('127.0.0.1', 5052, debug=True)
