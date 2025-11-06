#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
HWPX 파일 텍스트 추출 스크립트
HWPX는 ZIP 압축된 XML 파일 구조이므로 XML에서 텍스트를 추출합니다.
"""

import sys
import zipfile
import xml.etree.ElementTree as ET
import os

def extract_text_from_hwpx(hwpx_path):
    """HWPX 파일에서 텍스트 추출"""

    try:
        # HWPX 파일을 ZIP으로 열기
        with zipfile.ZipFile(hwpx_path, 'r') as zf:
            # 파일 목록 확인
            file_list = zf.namelist()

            # section 파일들 찾기 (Contents/section0.xml, section1.xml, ...)
            section_files = [f for f in file_list if f.startswith('Contents/section') and f.endswith('.xml')]
            section_files.sort()

            all_text = []

            for section_file in section_files:
                try:
                    # XML 파일 읽기
                    xml_content = zf.read(section_file)

                    # XML 파싱
                    root = ET.fromstring(xml_content)

                    # 네임스페이스 정의
                    namespaces = {
                        'hp': 'http://www.hancom.co.kr/hwpml/2011/paragraph',
                        'hc': 'http://www.hancom.co.kr/hwpml/2011/head'
                    }

                    # 모든 텍스트 노드 추출
                    # <hp:t> 태그에 실제 텍스트가 있음
                    text_nodes = root.findall('.//hp:t', namespaces)

                    for node in text_nodes:
                        if node.text:
                            all_text.append(node.text)

                    # 네임스페이스 없이도 시도
                    if not text_nodes:
                        for elem in root.iter():
                            if elem.tag.endswith('}t') or elem.tag == 't':
                                if elem.text:
                                    all_text.append(elem.text)

                except Exception as e:
                    # 개별 섹션 파싱 실패는 무시하고 계속
                    continue

            # 모든 텍스트 합치기
            result = '\n'.join(all_text)

            if result:
                return result
            else:
                return "ERROR: No text extracted from HWPX file"

    except zipfile.BadZipFile:
        return "ERROR: Not a valid HWPX (ZIP) file"
    except Exception as e:
        return f"ERROR: {str(e)}"

if __name__ == "__main__":
    if len(sys.argv) != 2:
        print("Usage: python3 extract_hwpx_text.py <hwpx_file_path>")
        sys.exit(1)

    hwpx_file = sys.argv[1]

    if not os.path.exists(hwpx_file):
        print(f"ERROR: File not found: {hwpx_file}")
        sys.exit(1)

    text = extract_text_from_hwpx(hwpx_file)
    print(text)
