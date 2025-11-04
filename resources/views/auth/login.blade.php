{{-- [BEGIN nara:login_form] --}}
@extends('layouts.app')

@section('title', '로그인')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h4 class="mb-0">로그인</h4>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('login') }}">
                    @csrf

                    <div class="mb-3">
                        <label for="email" class="form-label">이메일</label>
                        <input type="email" 
                               class="form-control @error('email') is-invalid @enderror" 
                               id="email" 
                               name="email" 
                               value="{{ old('email') }}" 
                               required 
                               autofocus>
                        @error('email')
                            <div class="invalid-feedback">
                                {{ $message }}
                            </div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label">비밀번호</label>
                        <input type="password" 
                               class="form-control @error('password') is-invalid @enderror" 
                               id="password" 
                               name="password" 
                               required>
                        @error('password')
                            <div class="invalid-feedback">
                                {{ $message }}
                            </div>
                        @enderror
                    </div>

                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="remember" name="remember">
                        <label class="form-check-label" for="remember">
                            로그인 상태 유지
                        </label>
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">
                            로그인
                        </button>
                    </div>
                </form>

                <div class="text-center mt-3">
                    <p class="mb-0">
                        계정이 없으신가요? 
                        <a href="{{ route('register') }}" class="text-primary">회원가입</a>
                    </p>
                </div>
            </div>
        </div>

        @if(config('app.env') === 'local')
        <div class="col-md-6 mt-4">
            <div class="card border-warning">
                <div class="card-header bg-warning text-dark">
                    <h5 class="mb-0">
                        <i class="bi bi-info-circle me-2"></i>
                        개발용 테스트 계정
                    </h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-info mb-3">
                        <small>
                            <i class="bi bi-lightbulb me-1"></i>
                            개발 단계에서만 표시됩니다. 관리자 계정으로 테스트하세요.
                        </small>
                    </div>

                    <div class="row g-3">
                        <div class="col-12">
                            <div class="border rounded p-3 mb-2 bg-light">
                                <h6 class="text-success mb-2">
                                    <i class="bi bi-person-gear me-1"></i>
                                    관리자
                                </h6>
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <div><strong>이메일:</strong> admin@nara.com</div>
                                        <div><strong>비밀번호:</strong> admin123</div>
                                    </div>
                                    <button type="button" 
                                            class="btn btn-sm btn-outline-success quick-login"
                                            data-email="admin@nara.com" 
                                            data-password="admin123">
                                        빠른 로그인
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="text-center mt-3">
                        <small class="text-muted">
                            <i class="bi bi-shield-check me-1"></i>
                            이 정보는 개발 환경에서만 표시됩니다.
                        </small>
                    </div>
                </div>
            </div>
        </div>
        @endif
    </div>
</div>

@push('scripts')
@if(config('app.env') === 'local')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const quickLoginButtons = document.querySelectorAll('.quick-login');
    
    quickLoginButtons.forEach(function(button) {
        button.addEventListener('click', function() {
            const email = this.getAttribute('data-email');
            const password = this.getAttribute('data-password');
            
            // 폼 필드에 자동 입력
            document.getElementById('email').value = email;
            document.getElementById('password').value = password;
            
            // 시각적 피드백
            this.innerHTML = '<i class="bi bi-check2 me-1"></i>입력됨';
            this.classList.remove('btn-outline-primary', 'btn-outline-success');
            this.classList.add('btn-success');
            
            // 이메일 필드 포커스
            document.getElementById('email').focus();
            
            setTimeout(() => {
                this.innerHTML = '빠른 로그인';
                this.classList.remove('btn-success');
                if (email.includes('admin')) {
                    this.classList.add('btn-outline-success');
                } else {
                    this.classList.add('btn-outline-primary');
                }
            }, 1500);
        });
    });
});
</script>
@endif
@endpush

@endsection
{{-- [END nara:login_form] --}}