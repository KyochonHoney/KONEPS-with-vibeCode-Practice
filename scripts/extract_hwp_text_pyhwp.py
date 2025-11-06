#!/home/tideflo/nara/public_html/storage/hwp_venv/bin/python
# -*- coding: utf-8 -*-
"""
pyhwp 라이브러리를 사용한 HWP 파일 텍스트 추출 스크립트
"""

import sys
import os

def extract_text_from_hwp(hwp_path):
    """pyhwp를 사용하여 HWP 파일에서 텍스트 추출"""
    try:
        from hwp5.binmodel import Hwp5File
        from hwp5 import plat
        from hwp5.xmlmodel import Hwp5File as XmlHwp5File

        hwp = Hwp5File(hwp_path)

        # 텍스트 추출
        text_parts = []

        # 모든 섹션 순회
        bodytext = hwp.bodytext
        for section in bodytext.sections:
            try:
                # XML 변환 후 텍스트 추출
                xml_section = section
                text_content = []

                # 바이너리에서 직접 텍스트 추출 시도
                for record in section.records():
                    if hasattr(record, 'text'):
                        text_content.append(record.text)

                if text_content:
                    text_parts.append(' '.join(text_content))

            except Exception as e:
                # 섹션 처리 실패 시 계속 진행
                continue

        return '\n'.join(text_parts)

    except Exception as e:
        return f"ERROR: {str(e)}"

if __name__ == "__main__":
    if len(sys.argv) != 2:
        print("Usage: extract_hwp_text_pyhwp.py <hwp_file_path>")
        sys.exit(1)

    hwp_file = sys.argv[1]
    text = extract_text_from_hwp(hwp_file)
    print(text)
