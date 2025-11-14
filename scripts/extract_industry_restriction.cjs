#!/usr/bin/env node

/**
 * Playwright 기반 업종제한사항 추출 스크립트
 *
 * 나라장터 공고 상세 페이지에서 업종제한사항 HTML을 파싱하여
 * 업종코드(4자리 숫자)를 추출합니다.
 *
 * Usage: node extract_industry_restriction.js <detail_url>
 */

const { chromium } = require('playwright');

async function extractIndustryRestriction(url) {
    const browser = await chromium.launch({
        headless: true,
        args: ['--no-sandbox', '--disable-setuid-sandbox']
    });

    try {
        const page = await browser.newPage();

        // User-Agent 설정
        await page.setExtraHTTPHeaders({
            'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
        });

        // 페이지 로드
        await page.goto(url, {
            waitUntil: 'networkidle',
            timeout: 30000
        });

        // 업종제한사항 요소 대기
        await page.waitForTimeout(2000);

        // 업종제한사항 텍스트 추출
        const industryText = await page.evaluate(() => {
            // 방법 1: ID로 직접 찾기
            const element = document.querySelector('#mf_wfm_container_mainWframe_txtIntpLmtCn');
            if (element) {
                return element.innerText || element.textContent;
            }

            // 방법 2: 테이블 구조에서 찾기
            const thElements = document.querySelectorAll('th');
            for (const th of thElements) {
                if (th.textContent.includes('업종제한사항')) {
                    const td = th.closest('tr').querySelector('td');
                    if (td) {
                        return td.innerText || td.textContent;
                    }
                }
            }

            return '';
        });

        if (!industryText) {
            return {
                success: true,
                industry_text: '',
                found_codes: [],
                message: '업종제한사항 없음'
            };
        }

        // 4자리 숫자 추출 (괄호 안의 업종코드)
        // 예: "소프트웨어사업자(컴퓨터관련서비스사업)(1468)" → 1468
        // 예: "정보통신공사업(0036)" → 0036
        const codeMatches = industryText.match(/\((\d{4})\)/g);
        const foundCodes = codeMatches
            ? [...new Set(codeMatches.map(match => match.replace(/[()]/g, '')))]
            : [];

        return {
            success: true,
            industry_text: industryText,
            found_codes: foundCodes
        };

    } catch (error) {
        return {
            success: false,
            error: error.message
        };
    } finally {
        await browser.close();
    }
}

// 메인 실행
(async () => {
    const url = process.argv[2];

    if (!url) {
        console.log(JSON.stringify({
            success: false,
            error: 'URL이 제공되지 않음'
        }));
        process.exit(1);
    }

    const result = await extractIndustryRestriction(url);
    console.log(JSON.stringify(result));
})();
