# import cv2
# import sys
# import os
# import json
# import zxingcpp
# import argparse
#
#
# def parse_barcode(filename):
#     img = cv2.imread(filename)
#     results = zxingcpp.read_barcodes(img)
#
#     if len(results) > 1:
#         sys.stdout.write("More than 1 codes found.")
#         sys.exit(-1)
#
#     if len(results) == 0:
#         sys.stdout.write("Could not find any barcode.")
#         sys.exit(-2)
#
#     for result in results:
#         if result.format in (zxingcpp.EAN13, zxingcpp.UPCA):
#             sys.stdout.write(result.text)
#             sys.exit(0)
#         elif result.format == zxingcpp.QRCode:
#             json_data = dict()
#             data = result.text.split('|')
#             for elem in data:
#                 if '=' in elem:
#                     key, value = elem.split('=')
#                     json_data[key] = value
#                 else:
#                     json_data[elem] = None
#             sys.stdout.write(json.dumps(json_data, ensure_ascii=False))
#             sys.exit(0)
#         else:
#             sys.stdout.write(f"Invalid format barcode: {result.format}.")
#             sys.exit(-3)
#
#
# if __name__ == '__main__':
#     parser = argparse.ArgumentParser(description='Decode barcode')
#     parser.add_argument('filename', type=str, help='Image filename')
#     args = parser.parse_args()
#     if not os.path.exists(args.filename):
#         sys.stdout.write(f"Not found file {args.filename}.")
#         sys.exit(-4)
#     parse_barcode(args.filename)


import cv2
import sys
import os
import json
import zxingcpp
import argparse


def parse_barcode(filename):
    response = {
        "status": False,
        "message": "",
        "data": None
    }

    img = cv2.imread(filename)
    results = zxingcpp.read_barcodes(img)

    if len(results) > 1:
        response["message"] = "More than 1 codes found."
        return response

    if len(results) == 0:
        response["message"] = "Could not find any barcode."
        return response

    for result in results:
        if result.format in (zxingcpp.EAN13, zxingcpp.UPCA):
            response["status"] = True
            response["type"] = 'ean13'
            response["data"] = result.text
            return response
        elif result.format == zxingcpp.QRCode:
            json_data = dict()
            data = result.text.split('|')
            for elem in data:
                if '=' in elem:
                    key, value = elem.split('=')
                    json_data[key] = value
                else:
                    json_data[elem] = None
            response["status"] = True
            response["type"] = 'qrcode'
            response["data"] = json_data
            return response
        else:
            response["message"] = f"Invalid format barcode: {result.format}."
            return response


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description='Decode barcode')
    parser.add_argument('filename', type=str, help='Image filename')
    args = parser.parse_args()
    if not os.path.exists(args.filename):
        print(json.dumps({"status": False, "message": f"Not found file {args.filename}."}, ensure_ascii=False))
        sys.exit(-4)
    response = parse_barcode(args.filename)
    print(json.dumps(response, ensure_ascii=False))

