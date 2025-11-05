#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
HWP 파일 텍스트 추출 스크립트
Usage: python3 extract_hwp_text.py <hwp_file_path>
"""

import sys
import olefile
import zlib
import struct

def extract_text_from_hwp(hwp_path):
    """HWP 파일에서 텍스트 추출"""
    try:
        # HWP 파일 열기 (OLE 파일 형식)
        ole = olefile.OleFileIO(hwp_path)

        # 모든 텍스트 조각 수집
        text_parts = []

        # BodyText 섹션에서 텍스트 추출
        # HWP 파일은 여러 섹션(Section)으로 구성됨
        for i in range(4096):  # 최대 4096개 섹션 확인
            section_name = f'BodyText/Section{i}'
            if ole.exists(section_name):
                try:
                    stream = ole.openstream(section_name)
                    data = stream.read()

                    # zlib 압축 해제 시도
                    try:
                        decompressed = zlib.decompress(data, -15)
                    except:
                        # 압축되지 않은 데이터일 수 있음
                        decompressed = data

                    # 텍스트 추출 (간단한 방법)
                    # HWP 형식: 각 문자는 2바이트 (UTF-16LE)
                    text = ''
                    i = 0
                    while i < len(decompressed) - 1:
                        try:
                            # 2바이트씩 읽어서 문자로 변환
                            char_bytes = decompressed[i:i+2]
                            if len(char_bytes) == 2:
                                char_code = struct.unpack('<H', char_bytes)[0]

                                # 일반적인 텍스트 범위 (한글, 영문, 숫자, 기호)
                                if (0x20 <= char_code <= 0x7E) or \
                                   (0xAC00 <= char_code <= 0xD7A3) or \
                                   (char_code in [0x000A, 0x000D]):  # 개행 문자
                                    text += chr(char_code)
                                elif char_code == 0x0000:
                                    # NULL 문자는 공백으로
                                    text += ' '
                            i += 2
                        except:
                            i += 2
                            continue

                    if text.strip():
                        text_parts.append(text)

                except Exception as e:
                    # 이 섹션 파싱 실패, 다음 섹션 시도
                    continue
            else:
                # 더 이상 섹션이 없으면 종료
                break

        ole.close()

        # 모든 텍스트 조각 합치기
        full_text = '\n'.join(text_parts)
        return full_text

    except Exception as e:
        return f"ERROR: {str(e)}"

if __name__ == "__main__":
    if len(sys.argv) != 2:
        print("Usage: python3 extract_hwp_text.py <hwp_file_path>")
        sys.exit(1)

    hwp_file = sys.argv[1]
    text = extract_text_from_hwp(hwp_file)
    print(text)
