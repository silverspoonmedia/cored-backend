<?php

return [
    'connection' => [
        'list_success' => 'AI 연결을 가져왔습니다.',
        'show_success' => 'AI 연결을 가져왔습니다.',
        'created' => 'AI 연결을 만들었습니다. 테스트를 실행해 활성화하세요.',
        'updated' => 'AI 연결을 수정했습니다. 다시 테스트해 활성화하세요.',
        'deleted' => 'AI 연결을 삭제했습니다.',
        'default_updated' => '기본 AI 연결을 변경했습니다.',
        'slug_required' => '연결 슬러그는 필수입니다.',
        'slug_taken' => '해당 연결 슬러그는 이미 사용 중입니다.',
        'label_required' => '연결 라벨은 필수입니다.',
        'endpoint_invalid' => '엔드포인트는 유효한 http(s) URL이어야 합니다.',
        'test_model_required' => '이 연결을 검사하려면 테스트 모델이 필요합니다.',
        'payload_invalid' => '저장된 시크릿이 유효하지 않습니다. API 키를 다시 입력하세요.',
        'payload_missing' => '시크릿에 필수 필드가 없습니다: :field',
        'payload_unknown_key' => '알 수 없는 시크릿 필드: :field',
        'default_requires_active' => '기본값이 될 수 있는 것은 활성(테스트됨) 연결뿐입니다.',
        'delete_requires_default_handover' => '삭제하기 전에 다른 연결을 기본값으로 지정하세요.',
        'test_not_configured' => '엔드포인트, API 키 또는 테스트 모델이 없습니다.',
        'test_unreachable' => '엔드포인트에 연결할 수 없습니다: :error',
        'test_unauthorized' => '엔드포인트가 API 키를 거부했습니다 (401/403).',
        'test_http_failed' => '엔드포인트가 HTTP :status 를 반환했습니다.',
        'test_http_failed_with_body' => '엔드포인트가 HTTP :status 를 반환했습니다: :detail',
        'test_passed' => '연결 테스트를 통과했습니다.',
    ],
];
