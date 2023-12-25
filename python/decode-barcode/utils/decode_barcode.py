import cv2
import sys
import os
import json
import zxingcpp
import argparse
import numpy as np

BARCODE_TYPE = 'barcode'
QR_CODE_TYPE = 'qr_code'


def parse_barcode(image_stream):
    """
    Error format result:
        {
          "status": false,
          "data": null,
          "error": "Could not find any barcode.",
          "status_code": -2
        }

    QR code format result:
        {
          "status": true,
          "status_code": 0,
          "data": {
            "ST00012": null,
            "NAME": "ООО \"ТРЦ\"",
            "PERSONALACC": "40702810464000047940",
            "BANKNAME": "Томское отделение №8616 ПАО Сбербанк г.Томск",
            "BIC": "046902606",
            "CORRESPACC": "30101810800000000606",
            "SUM": "56915",
            "PAYEEINN": "7017374198",
            "PERSACC": "207004008991",
            "PAYMPERIOD": "052023"
          },
          "type": "qr_code"
        }

    Barcode format result:
        {
          "status": true,
          "status_code": 0,
          "data": "036000291452",
          "type": "barcode"
        }
    :param filename:
    :return:
    """

    json_result = {
        'status': False,
        'data': None,
        'status_code': 0
    }

    file_bytes = np.asarray(bytearray(image_stream.read()), dtype=np.uint8)
    img = cv2.imdecode(file_bytes, cv2.IMREAD_COLOR)
    results = zxingcpp.read_barcodes(img)

    if len(results) > 1:
        json_result['error'] = 'More than 1 codes found.'
        json_result['status_code'] = -1
        return json_result

    if len(results) == 0:
        json_result['error'] = 'Could not find any barcode.'
        json_result['status_code'] = -2
        return json_result

    result = results[0]
    if result.format in (zxingcpp.EAN13, zxingcpp.UPCA, zxingcpp.QRCode):
        if result.format in (zxingcpp.EAN13, zxingcpp.UPCA):
            json_result['data'] = result.text
            json_result['type'] = BARCODE_TYPE
        elif result.format == zxingcpp.QRCode:
            json_data = dict()
            data = result.text.split('|')
            for elem in data:
                if '=' in elem:
                    key, value = elem.split('=')
                    json_data[key.upper()] = value
                else:
                    json_data[elem.upper()] = None
            json_result['data'] = json_data
            json_result['type'] = QR_CODE_TYPE
        json_result['status'] = True
        return json_result
    else:
        json_result['error'] = f'Invalid format barcode: {result.format}.'
        json_result['status_code'] = -3
        return json_result


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description='Decode barcode')
    parser.add_argument('filename', type=str, help='Image filename')
    args = parser.parse_args()
    result = parse_barcode(args.filename)
    sys.stdout.write(json.dumps(result, ensure_ascii=False))
    sys.exit(result['status_code'])
