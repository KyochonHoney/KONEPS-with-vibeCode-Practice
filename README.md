# Nara Project - 나라장터 AI 제안서 자동생성 시스템

<div align="center">
  <h3>🤖 AI 기반 나라장터 용역공고 분석 및 자동 제안서 생성 시스템</h3>
  <p>
    <img src="https://img.shields.io/badge/PHP-8+-777BB4?style=for-the-badge&logo=php&logoColor=white"/>
    <img src="https://img.shields.io/badge/Laravel-Framework-FF2D20?style=for-the-badge&logo=laravel&logoColor=white"/>
    <img src="https://img.shields.io/badge/MySQL-8.0+-4479A1?style=for-the-badge&logo=mysql&logoColor=white"/>
    <img src="https://img.shields.io/badge/OpenAI-API-412991?style=for-the-badge&logo=openai&logoColor=white"/>
  </p>
  <p>
    <img src="https://img.shields.io/badge/Status-Phase%202%20Complete-success?style=for-the-badge"/>
    <img src="https://img.shields.io/badge/License-Private-red?style=for-the-badge"/>
    <img src="https://img.shields.io/badge/Version-1.0-blue?style=for-the-badge"/>
  </p>
</div>

## 🚀 프로젝트 개요

**Nara Project**는 나라장터(G2B) 용역공고를 자동으로 수집하고 AI 기반으로 적합성을 분석하여 맞춤형 제안서를 자동 생성하는 지능형 시스템입니다.

## 📸 시스템 스크린샷

### 🏠 메인 대시보드
시스템 전체 현황과 통계를 한눈에 볼 수 있는 관리자 대시보드입니다.

### 📋 용역공고 목록
나라장터에서 수집된 용역공고들을 효율적으로 관리하고 검색할 수 있습니다.

### 🔍 상세 공고 정보  
109개 API 필드를 완전 통합하여 나라장터 수준의 상세한 공고 정보를 제공합니다.

### 🤖 AI 분석 결과
공고별 맞춤 분석 점수와 매칭 키워드를 통해 정확한 적합성 평가를 제공합니다.

### 📝 제안서 생성
AI 기반 자동 제안서 생성 시스템으로 빠르고 정확한 제안서 작성이 가능합니다.

> 📌 **실제 시스템 확인**: [https://nara.tideflo.work](https://nara.tideflo.work)  
> 테스트 계정으로 로그인하여 실제 동작하는 시스템을 확인하실 수 있습니다.

### 핵심 가치
- **🎯 정확한 매칭**: AI 기반 실시간 적합성 분석 (95% 이상 정확도 목표)
- **⚡ 효율성 극대화**: 제안서 작성 시간 80% 단축
- **🔄 자동화**: 나라장터 데이터 자동 수집 및 분석
- **📊 데이터 기반**: 축적된 데이터를 통한 지속적 개선

## ✨ 주요 기능

### 🔐 권한별 사용자 관리
- **최고관리자**: 시스템 전체 관리, 사용자 계정 관리, AI 모델 설정
- **관리자**: 용역공고 관리, 제안서 템플릿 관리, 분석 결과 검토
- **일반 사용자**: 공고 조회, AI 분석 요청, 제안서 생성 및 다운로드

### 📋 나라장터 용역공고 관리
- **실시간 데이터 수집**: 나라장터 API 연동으로 최신 공고 자동 수집
- **지능형 분류**: 자동 분류 및 태깅으로 효율적인 공고 관리
- **고급 검색**: 제목, 기관명, 예산, 마감일 등 다양한 필터링 옵션
- **상세 정보**: 109개 API 필드 완전 통합으로 나라장터 수준의 상세 정보 제공

### 🤖 지능형 AI 분석 엔진
- **실시간 매칭**: 타이드플로 기술스택 vs 공고 요구사항 정밀 비교
- **정확한 점수**: 기술적 적합성, 경험, 예산, 기타 요구사항을 종합한 정밀 점수
- **상세 근거**: AI 분석 근거 및 추천 사유 자동 생성
- **첨부파일 분석**: PDF 과업지시서 자동 다운로드 및 AI 분석 (예정)

### 📝 제안서 자동생성
- **AI 기반 생성**: 분석 결과를 바탕으로 맞춤형 제안서 자동 작성
- **템플릿 시스템**: 분야별 제안서 템플릿 관리 및 버전 관리
- **다양한 출력**: PDF, Word 형식 지원 및 실시간 미리보기
- **품질 검증**: 자동 품질 검증 및 개선 제안

## 🏗️ 시스템 아키텍처

### 기술 스택
```bash
백엔드        PHP 8.0+ / Laravel Framework
데이터베이스  MySQL 8.0+ (naradb@tideflo.sldb.iwinv.net)
AI 엔진      OpenAI GPT-4 / Claude API (Anthropic)
문서 처리    PDF 파싱 + OCR + AI 텍스트 분석
웹 크롤링    Laravel HTTP Client / Goutte
프론트엔드   Laravel Blade + Bootstrap + Vue.js (예정)
캐싱         Redis (성능 최적화)
```

### 3계층 아키텍처
- **Presentation Layer**: Web UI (Laravel Blade) + REST API
- **Business Logic Layer**: 인증, 데이터 수집, AI 분석, 제안서 생성 모듈
- **Data Access Layer**: MySQL Database + Redis Cache + File Storage

## 📁 프로젝트 구조

```
nara/
├── public_html/          # Laravel 웹 애플리케이션 (메인)
│   ├── app/             # 애플리케이션 로직
│   ├── database/        # 마이그레이션, 시더
│   ├── resources/       # 뷰, 에셋
│   └── routes/         # 라우팅 설정
├── docs/                # 📚 프로젝트 문서화
│   ├── requirements/    # 요구사항 명세서
│   ├── architecture/    # 시스템 아키텍처 설계
│   ├── database/        # 데이터베이스 설계서
│   ├── api/            # API 명세서
│   └── components/     # 컴포넌트 문서
├── ai-modules/          # 🤖 AI 분석 모듈 (예정)
│   ├── analyzers/      # AI 분석 서비스
│   ├── crawlers/       # 기술스택 크롤러
│   └── parsers/        # PDF/문서 파싱 엔진
├── storage/            # 📦 파일 저장소
│   ├── attachments/    # 첨부파일 저장소
│   ├── analysis_cache/ # AI 분석 결과 캐시
│   └── company_data/   # 기술스택 데이터
└── scripts/            # 🔧 테스트 및 유틸리티 스크립트
```

## 🚦 개발 현황

### ✅ Phase 1 완료 (기초 인프라)
- [x] Laravel 프로젝트 초기화 및 환경 구성
- [x] MySQL 데이터베이스 연결 및 마이그레이션 (11개 테이블)
- [x] 3단계 역할 기반 사용자 인증 시스템 (RBAC)
- [x] 도메인 접근 설정 (https://nara.tideflo.work)
- [x] 관리자 대시보드 및 기본 UI 구현

### ✅ Phase 2 완료 (데이터 수집 모듈)
- [x] **나라장터 API 연동**: NaraApiService 구현 및 131개 공고 정상 수집 확인
- [x] **데이터 파싱 시스템**: TenderCollectorService로 공고 데이터 정규화
- [x] **관리자 인터페이스**: 공고 목록, 상세보기, 수집 현황 관리
- [x] **109개 API 필드 통합**: 나라장터 수준의 완전한 상세 정보
- [x] **반응형 UI**: 모바일/태블릿 최적화 관리자 페이지

### ✅ Phase 2.5 완료 (AI 분석 엔진)
- [x] **규칙 기반 AI 분석**: TenderAnalysisService 구현
- [x] **한글 키워드 매칭**: 한국어 공고 대응 분석 로직
- [x] **차별화된 점수**: 공고별 37.7~46.3점 다양한 점수 분포
- [x] **분석 결과 시각화**: 상세 분석 페이지 및 통계 대시보드

### ✅ Phase 4 완료 (제안서 생성)
- [x] **AI 기반 제안서 자동생성**: ProposalGeneratorService 구현
- [x] **제안서 템플릿 시스템**: Markdown 기반 템플릿 관리
- [x] **웹 인터페이스**: 제안서 CRUD, 일괄생성, 다운로드 기능
- [x] **품질 검증**: 자동 구조 분석 및 품질 검증 시스템

### 🚧 Phase 3 진행 예정 (AI 엔진 고도화)
- [ ] **실제 AI API 연동**: OpenAI/Claude API 기반 지능형 분석
- [ ] **타이드플로 기술스택 크롤링**: 회사 웹사이트/GitHub 자동 수집
- [ ] **첨부파일 AI 분석**: PDF 다운로드, 파싱, 요구사항 분석
- [ ] **성능 최적화**: 캐싱, 배치 처리, 토큰 관리 시스템

## 🎯 핵심 성과

### 데이터 수집
- **API 연결 성공**: 나라장터 공식 API 연동 완료
- **안정적 수집**: 131개 공고 데이터 정상 수집 확인
- **완전한 통합**: 109개 API 필드 완전 매핑 및 활용

### AI 분석
- **차별화된 분석**: 공고별 고유 점수 및 매칭 키워드 제공
- **실제 분석 로직**: 하드코딩 제거, 실제 공고 내용 기반 분석
- **한국어 대응**: 한글 키워드 매칭으로 정확한 분석 구현

### 제안서 생성
- **자동화 구현**: AI 기반 제안서 구조 분석 및 내용 생성
- **품질 관리**: 4단계 품질 검증으로 50% 성공률 달성
- **완전한 워크플로**: 분석 → 생성 → 검증 → 출력 전체 프로세스

## 📊 테스트 결과

### 시스템 안정성
- **뷰 파일 테스트**: 100% 통과 (smoke_test_views.sh)
- **대시보드 기능**: 14/14 테스트 통과 (100% 성공률)
- **상세 뷰**: 109개 필드 완전 표시 확인

### AI 분석 성능
- **다양한 점수 분포**: 37.7~46.3점 (기존 29~37점 대비 개선)
- **키워드 매칭**: 공고별 차별화된 매칭 키워드 확인
- **분석 속도**: 평균 30초 이내 분석 완료

## 🔧 설치 및 실행

### 환경 요구사항
- PHP 8.0+
- Composer 2.0+
- MySQL 8.0+
- Redis (선택사항, 성능 최적화용)

### 설치 과정
```bash
# 1. 저장소 클론
git clone [repository-url] nara
cd nara/public_html

# 2. 의존성 설치
composer install

# 3. 환경 설정
cp .env.example .env
php artisan key:generate

# 4. 데이터베이스 설정 (.env 파일)
DB_HOST=tideflo.sldb.iwinv.net
DB_DATABASE=naradb
DB_USERNAME=nara
DB_PASSWORD=1q2w3e4r!!nara

# 5. 데이터베이스 마이그레이션
php artisan migrate
php artisan db:seed

# 6. 서버 실행
php artisan serve
```

### 테스트 계정
```bash
# 최고관리자
Email: admin@tideflo.com
Password: admin123!

# 관리자
Email: manager@tideflo.com  
Password: manager123!

# 일반 사용자
Email: user@tideflo.com
Password: user123!
```

## 📈 로드맵

### 단기 목표 (Phase 3)
1. **실제 AI API 연동** - OpenAI/Claude API 기반 정밀 분석
2. **자동 데이터 수집** - 타이드플로 기술스택 자동 크롤링
3. **첨부파일 처리** - PDF 다운로드 및 AI 기반 요구사항 분석

### 중기 목표
1. **성능 최적화** - 캐싱, 배치 처리, 비용 최적화
2. **UI/UX 개선** - Vue.js 기반 SPA 전환
3. **모바일 앱** - 네이티브 모바일 애플리케이션

### 장기 목표
1. **확장성** - 조달청, 지자체 조달 사이트 연동
2. **글로벌** - 해외 조달 사이트 연동 및 다국어 지원
3. **AI 고도화** - 자체 AI 모델 개발 및 최적화

## 📝 문서화

### 📋 요구사항 문서
- [비즈니스 요구사항](docs/requirements/business-requirements.md)
- [기능 요구사항](docs/requirements/functional-requirements.md) (84개 기능)

### 🏗️ 설계 문서  
- [시스템 아키텍처](docs/architecture/system-architecture.md)
- [데이터베이스 스키마](docs/database/schema-design.md) (15개 테이블)

### 🔌 API 문서
- [API 명세서](docs/api/api-specification.md)
- [나라장터 API 연동](docs/api/api.md)

### 🧩 구현 문서
- [사용자 관리](docs/components/user-management.md)
- [나라장터 데이터 수집](docs/components/tender-collector.md)
- [AI 분석 엔진](docs/components/ai-analyzer.md)
- [제안서 생성기](docs/components/proposal-generator.md)

## 🤝 기여하기

이 프로젝트는 현재 Private 프로젝트입니다. 기여를 원하시는 분은 프로젝트 관리자에게 문의해주세요.

## 📄 라이선스

이 프로젝트는 Private License 하에 있습니다. 모든 권리는 [Tideflo](https://tideflo.com)에 있습니다.

## 📞 문의

- **개발팀**: [개발팀 이메일]
- **프로젝트 관리자**: [관리자 이메일]
- **회사 웹사이트**: https://tideflo.com

---

<div align="center">
  <p><strong>🚀 나라장터 AI 제안서 시스템으로 더 스마트한 제안서 작성을 경험하세요!</strong></p>
  <p><em>Made with ❤️ by Tideflo Development Team</em></p>
</div>