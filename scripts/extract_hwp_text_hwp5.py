#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
hwp5txt를 사용한 HWP 파일 텍스트 추출 스크립트
pyhwp 라이브러리의 hwp5txt 도구를 사용하여 가장 정확한 텍스트 추출을 제공합니다.
"""

import sys
import subprocess
import os

def extract_text_from_hwp(hwp_path):
    """hwp5txt를 사용하여 HWP 파일에서 텍스트 추출"""

    # hwp5txt 경로 (virtual environment)
    hwp5txt_path = '/home/tideflo/nara/public_html/storage/hwp_venv/bin/hwp5txt'

    # hwp5txt가 없으면 시스템 hwp5txt 시도
    if not os.path.exists(hwp5txt_path):
        hwp5txt_path = 'hwp5txt'

    try:
        # hwp5txt 실행
        result = subprocess.run(
            [hwp5txt_path, hwp_path],
            capture_output=True,
            text=True,
            timeout=30
        )

        if result.returncode == 0:
            return result.stdout
        else:
            return f"ERROR: hwp5txt failed with return code {result.returncode}\n{result.stderr}"

    except subprocess.TimeoutExpired:
        return "ERROR: hwp5txt timeout (30 seconds)"
    except FileNotFoundError:
        return "ERROR: hwp5txt not found. Please install pyhwp package."
    except Exception as e:
        return f"ERROR: {str(e)}"

if __name__ == "__main__":
    if len(sys.argv) != 2:
        print("Usage: python3 extract_hwp_text_hwp5.py <hwp_file_path>")
        sys.exit(1)

    hwp_file = sys.argv[1]

    if not os.path.exists(hwp_file):
        print(f"ERROR: File not found: {hwp_file}")
        sys.exit(1)

    text = extract_text_from_hwp(hwp_file)
    print(text)
