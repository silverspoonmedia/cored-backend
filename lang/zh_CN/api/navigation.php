<?php

return [
    'tree_success' => '导航树已成功获取。',
    'layouts_success' => '导航布局已成功获取。',
    'list_success' => '菜单列表已成功获取。',
    'show_success' => '菜单已成功获取。',
    'trashed_success' => '已删除菜单列表已成功获取。',
    'created' => '菜单已成功创建。',
    'updated' => '菜单已成功更新。',
    'deleted' => '菜单已成功删除。',
    'restored' => '菜单已成功恢复。',
    'not_found' => '未找到菜单。',
    'has_children' => '此菜单包含子项。传入 cascade=true 可将其与所有后代一起删除。',
    'invalid_route' => 'route 必须是已注册的路由名称。',
    'position' => [
        'reference_required' => '执行前后定位时必须提供 reference_id。',
        'reference_not_in_group' => 'reference_id 不属于同一兄弟项分组。',
        'invalid_position' => 'position 必须为 before 或 after。',
        'reference_same_menu' => 'reference_id 不能与当前菜单相同。',
        'reference_wrong_group' => '参考菜单必须与目标分组共享 parent_id 和 type。',
    ],
    'parent_restore_failed' => '无法恢复：父菜单不存在或仍处于删除状态。请先恢复父菜单。',
];
