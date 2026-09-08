<?php

return [
    'list_success' => '알림 목록을 가져왔습니다.',
    'unread_count_success' => '읽지 않은 알림 수를 가져왔습니다.',
    'marked_read' => '알림을 읽음으로 표시했습니다.',
    'marked_all_read' => '모든 알림을 읽음으로 표시했습니다.',
    'not_visible' => '이 알림에 접근할 권한이 없습니다.',
    // Message templates per notification type. Replacements come from the
    // notification `data` payload (:name, :email, :actor, :role, …).
    'types' => [
        'user_created' => ':actor님이 새 사용자 :name(:email)을(를) 추가했습니다.',
        'user_updated' => ':actor님이 사용자 :name(:email)을(를) 수정했습니다.',
        'user_deleted' => ':actor님이 사용자 :name(:email)을(를) 삭제했습니다.',
        'account_updated' => ':actor님이 회원님의 계정을 수정했습니다.',
        'service_subscription_grace' => ':service 구독이 종료되었습니다. :grace_days일 이내(:grace_ends_at까지)에 갱신하지 않으면 초과된 파일이 무료 등급 한도에 맞게 삭제됩니다.',
        'service_subscription_expired' => ':service 구독 유예 기간이 종료되었습니다. 무료 등급 저장 용량이 적용됩니다(파일 :deleted개 삭제됨).',
        'inference_unregistered_model' => ':provider_slug의 미등록 모델 :resolved_model이(가) 임시 요금으로 청구되었습니다(1M당 입력 $:input_per_million_usd / 출력 $:output_per_million_usd). 모델 카탈로그에서 분류하여 청구를 확정하십시오.',
        'vtual_management_invite_received' => ':inviter 님이 :management에 :role(으)로 초대했습니다.',
        'vtual_management_creator_invite_received' => ':inviter 님이 vTuber 프로필을 :management에 연결하도록 초대했습니다.',
    ],
    'audiences' => [
        'list_success' => '알림 대상 목록을 불러왔습니다.',
        'show_success' => '알림 대상을 불러왔습니다.',
        'updated' => '알림 대상을 수정했습니다.',
        'unknown_type' => '알 수 없는 알림 유형입니다.',
    ],
];
