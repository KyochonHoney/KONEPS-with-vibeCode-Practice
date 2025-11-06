#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
개선된 HWP 파일 텍스트 추출 스크립트
"""

import sys
import olefile
import zlib
import struct

def extract_text_from_hwp(hwp_path):
    """HWP 파일에서 텍스트 추출 - 개선된 버전"""
    try:
        ole = olefile.OleFileIO(hwp_path)
        text_parts = []

        # BodyText 섹션에서 텍스트 추출
        for i in range(4096):
            section_name = f'BodyText/Section{i}'
            if not ole.exists(section_name):
                break
                
            try:
                stream = ole.openstream(section_name)
                data = stream.read()

                # zlib 압축 해제
                try:
                    decompressed = zlib.decompress(data, -15)
                except:
                    decompressed = data

                # 텍스트 추출 - 개선된 방법
                text = bytearray()
                pos = 0
                
                while pos < len(decompressed):
                    # HWP 레코드 구조: 태그(4바이트) + 레벨(4바이트) + 크기(4바이트) + 데이터
                    if pos + 12 > len(decompressed):
                        break
                        
                    try:
                        # 레코드 헤더 읽기
                        tag = struct.unpack('<I', decompressed[pos:pos+4])[0]
                        level = struct.unpack('<I', decompressed[pos+4:pos+8])[0]
                        size = struct.unpack('<I', decompressed[pos+8:pos+12])[0]
                        
                        pos += 12
                        
                        # 데이터 읽기
                        if pos + size > len(decompressed):
                            break
                            
                        record_data = decompressed[pos:pos+size]
                        pos += size
                        
                        # HWPTAG_PARA_TEXT (67) - 텍스트 데이터
                        if tag == 67:
                            # UTF-16LE로 디코드
                            try:
                                decoded_text = record_data.decode('utf-16le', errors='ignore')
                                text.extend(decoded_text.encode('utf-8'))
                                text.extend(b'\n')
                            except:
                                pass
                                
                    except:
                        # 파싱 실패 시 다음 바이트로 이동
                        pos += 1
                        continue

                if text:
                    text_parts.append(text.decode('utf-8', errors='ignore'))

            except Exception as e:
                continue

        ole.close()
        
        full_text = '\n'.join(text_parts)
        return full_text

    except Exception as e:
        return f"ERROR: {str(e)}"

if __name__ == "__main__":
    if len(sys.argv) != 2:
        print("Usage: python3 extract_hwp_text_improved.py <hwp_file_path>")
        sys.exit(1)

    hwp_file = sys.argv[1]
    text = extract_text_from_hwp(hwp_file)
    print(text)
