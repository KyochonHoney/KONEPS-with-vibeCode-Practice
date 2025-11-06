#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
HWP 파일 텍스트 추출 스크립트 - LibreOffice 사용
"""

import sys
import subprocess
import os
import tempfile

def extract_text_from_hwp(hwp_path):
    """LibreOffice를 사용하여 HWP 파일에서 텍스트 추출"""
    try:
        # 임시 디렉토리 생성
        with tempfile.TemporaryDirectory() as temp_dir:
            # LibreOffice로 HWP를 TXT로 변환
            txt_file = os.path.join(temp_dir, 'output.txt')

            # LibreOffice 변환 명령어
            cmd = [
                'libreoffice',
                '--headless',
                '--convert-to', 'txt:Text',
                '--outdir', temp_dir,
                hwp_path
            ]

            # 변환 실행
            result = subprocess.run(
                cmd,
                stdout=subprocess.PIPE,
                stderr=subprocess.PIPE,
                timeout=30
            )

            # 변환된 파일 찾기
            base_name = os.path.splitext(os.path.basename(hwp_path))[0]
            txt_file = os.path.join(temp_dir, f'{base_name}.txt')

            if os.path.exists(txt_file):
                with open(txt_file, 'r', encoding='utf-8') as f:
                    text = f.read()
                return text
            else:
                return f"ERROR: 변환된 텍스트 파일을 찾을 수 없습니다: {txt_file}"

    except subprocess.TimeoutExpired:
        return "ERROR: LibreOffice 변환 시간 초과"
    except Exception as e:
        return f"ERROR: {str(e)}"

if __name__ == "__main__":
    if len(sys.argv) != 2:
        print("Usage: python3 extract_hwp_text_libreoffice.py <hwp_file_path>")
        sys.exit(1)

    hwp_file = sys.argv[1]
    text = extract_text_from_hwp(hwp_file)
    print(text)
