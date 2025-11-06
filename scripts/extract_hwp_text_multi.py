#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
다중 방법을 사용한 강력한 HWP 파일 텍스트 추출 스크립트
여러 방법을 시도해서 최대한 많은 텍스트를 추출합니다.
"""

import sys
import olefile
import zlib
import struct

def method1_basic_extraction(hwp_path):
    """방법 1: 기본 UTF-16LE 추출"""
    try:
        ole = olefile.OleFileIO(hwp_path)
        text_parts = []

        for i in range(4096):
            section_name = f'BodyText/Section{i}'
            if not ole.exists(section_name):
                break

            try:
                stream = ole.openstream(section_name)
                data = stream.read()

                try:
                    decompressed = zlib.decompress(data, -15)
                except:
                    decompressed = data

                # UTF-16LE 디코딩 시도
                text = ''
                i = 0
                while i < len(decompressed) - 1:
                    try:
                        char_bytes = decompressed[i:i+2]
                        if len(char_bytes) == 2:
                            char_code = struct.unpack('<H', char_bytes)[0]

                            # 한글, 영문, 숫자, 공백
                            if (0x20 <= char_code <= 0x7E) or \
                               (0xAC00 <= char_code <= 0xD7A3) or \
                               (char_code in [0x000A, 0x000D, 0x0020]):
                                text += chr(char_code)
                        i += 2
                    except:
                        i += 2
                        continue

                if text.strip():
                    text_parts.append(text)

            except Exception as e:
                continue

        ole.close()
        return '\n'.join(text_parts)
    except:
        return ''

def method2_tag67_extraction(hwp_path):
    """방법 2: HWPTAG_PARA_TEXT (Tag 67) 파싱"""
    try:
        ole = olefile.OleFileIO(hwp_path)
        text_parts = []

        for i in range(4096):
            section_name = f'BodyText/Section{i}'
            if not ole.exists(section_name):
                break

            try:
                stream = ole.openstream(section_name)
                data = stream.read()

                try:
                    decompressed = zlib.decompress(data, -15)
                except:
                    decompressed = data

                text = bytearray()
                pos = 0

                while pos < len(decompressed):
                    if pos + 12 > len(decompressed):
                        break

                    try:
                        tag = struct.unpack('<I', decompressed[pos:pos+4])[0]
                        level = struct.unpack('<I', decompressed[pos+4:pos+8])[0]
                        size = struct.unpack('<I', decompressed[pos+8:pos+12])[0]

                        pos += 12

                        if pos + size > len(decompressed):
                            break

                        record_data = decompressed[pos:pos+size]
                        pos += size

                        # HWPTAG_PARA_TEXT (67)
                        if tag == 67:
                            try:
                                decoded_text = record_data.decode('utf-16le', errors='ignore')
                                text.extend(decoded_text.encode('utf-8'))
                                text.extend(b' ')
                            except:
                                pass

                    except:
                        pos += 1
                        continue

                if text:
                    text_parts.append(text.decode('utf-8', errors='ignore'))

            except:
                continue

        ole.close()
        return '\n'.join(text_parts)
    except:
        return ''

def method3_raw_search(hwp_path):
    """방법 3: 원시 바이트에서 직접 한글 검색"""
    try:
        ole = olefile.OleFileIO(hwp_path)
        text_parts = []

        for i in range(4096):
            section_name = f'BodyText/Section{i}'
            if not ole.exists(section_name):
                break

            try:
                stream = ole.openstream(section_name)
                data = stream.read()

                try:
                    decompressed = zlib.decompress(data, -15)
                except:
                    decompressed = data

                # UTF-16LE로 전체 디코드 시도
                try:
                    text = decompressed.decode('utf-16le', errors='ignore')
                    # 제어 문자 제거
                    text = ''.join(char if char.isprintable() or char in '\n\r\t ' else ' ' for char in text)
                    if text.strip():
                        text_parts.append(text)
                except:
                    pass

            except:
                continue

        ole.close()
        return '\n'.join(text_parts)
    except:
        return ''

def extract_text_from_hwp(hwp_path):
    """모든 방법을 시도하여 텍스트 추출"""

    results = []

    # 방법 1 시도
    text1 = method1_basic_extraction(hwp_path)
    if text1:
        results.append(text1)

    # 방법 2 시도
    text2 = method2_tag67_extraction(hwp_path)
    if text2:
        results.append(text2)

    # 방법 3 시도
    text3 = method3_raw_search(hwp_path)
    if text3:
        results.append(text3)

    # 가장 긴 결과 반환
    if results:
        return max(results, key=len)

    return "ERROR: 텍스트 추출 실패"

if __name__ == "__main__":
    if len(sys.argv) != 2:
        print("Usage: python3 extract_hwp_text_multi.py <hwp_file_path>")
        sys.exit(1)

    hwp_file = sys.argv[1]
    text = extract_text_from_hwp(hwp_file)
    print(text)
