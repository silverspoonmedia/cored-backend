<?php

return [
    'pong' => 'Pong',
    'data_not_found' => 'データが見つかりません。',
    'resource_not_found' => '指定されたデータが見つかりません。',
    'unauthorized' => 'この操作を行う権限がありません。',
    'updated' => '正常に更新されました。',
    'batch_invalid' => 'バッチペイロードには、1件以上のリクエストを含むリストを指定してください。',
    'batch_too_many' => 'バッチペイロードが最大10件のリクエストを超えています。',
    'batch_entry_invalid' => 'バッチエントリが不正です。',
    'batch_method_not_allowed' => 'バッチエントリはGETまたはHEADリクエストのみ許可されます。',
    'batch_path_not_allowed' => 'バッチエントリはこのAPIのみを対象にできます。',
    'batch_sub_failed' => 'バッチ内のリクエストの1つを処理できませんでした。',
    'batch_timeout' => 'バッチが時間割当を超過したため、残りのエントリはスキップされました。',
    'batch_ok' => 'バッチが完了しました。',
    'spa_origin_required' => 'このAPIには公式アプリケーション経由でのみアクセスできます。',
    'spa_csrf_issued' => 'CSRFトークンを発行しました。',
    'spa_csrf_mismatch' => 'CSRFトークンが一致しません。',
    'too_many_requests' => 'リクエストが多すぎます。間隔を空けて再試行してください。',
];
