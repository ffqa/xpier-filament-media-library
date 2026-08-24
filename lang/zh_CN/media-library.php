<?php

return [

    'navigation' => [
        'group' => '系统管理',
    ],

    'resources' => [
        'media_library' => [
            'navigation_label' => '媒体库',
            'model_label' => '媒体文件',
            'plural_label' => '媒体库',
        ],

        'media_folder' => [
            'navigation_label' => '媒体目录',
            'model_label' => '媒体目录',
            'plural_label' => '媒体目录',
        ],
    ],

    'media_library' => [
        'upload_section' => '上传到平台素材库',
        'upload_section_description' => '平台配图统一管理。文章/广告配图请用「从媒体库选择」弹窗（可在弹窗内上传）。',
        'upload_file' => '上传文件',
        'preview' => '预览',
        'no_preview' => '暂无预览',
        'folder' => '平台目录',
        'folder_helper' => '可在「媒体目录」里增删改。',
        'type' => '类型',
        'alt_text' => '替代文字 / 备注',
        'custom_properties' => '自定义属性',
        'custom_properties_helper' => '附加的键值对元数据，可用于前端展示或业务逻辑。',
        'manage_folders' => '管理目录',
        'upload_to_platform' => '上传到平台',
        'trashed_filter' => '已删除',
        'size' => '大小',
        'uploaded_at' => '上传时间',
        'uncategorized' => '未分类',
    ],

    'media_folder' => [
        'parent_folder' => '上级目录',
        'parent_helper' => '留空表示大类；子目录仅支持挂在一级大类下（暂不支持多级嵌套）。',
        'name' => '目录名称',
        'code' => '代号',
        'code_helper' => '英文短名；磁盘路径为 media/library/{大类}/{代号}/… 或 media/library/{大类}/…',
        'sort' => '排序',
        'is_active' => '启用',
        'storage_path' => '存储路径',
        'files_count' => '文件数',
        'updated_at' => '更新',
    ],

    'picker' => [
        'pick_from_library' => '从媒体库选择',
        'modal_heading' => '平台媒体库',
        'modal_description' => '像文件夹一样浏览：点目录进入下一级，点「上级」返回。可上传到当前目录。',
        'confirm' => '确认选择',
        'cancel' => '取消',
        'remove' => '移除',
        'no_cover' => '暂无封面图',
        'hint' => '配图请统一从平台媒体库选择或在弹窗内上传，勿在其他处零散上传。',
        'all' => '全部',
        'root' => '目录',
        'up' => '上级目录',
        'search' => '搜索',
        'search_placeholder' => '搜索文件名/备注…',
        'search_results' => '搜索: :keyword',
        'search_result_count' => '搜索结果 :count 项。点图片选中。',
        'media_count' => '媒体 :count 项（最多 100）。点文件夹进入下一级；点图片选中。悬停可看文件名/备注。',
        'empty' => '当前目录为空。可上传图片，或点面包屑切换目录。',
        'upload_to_current' => '上传到当前目录',
        'upload_disabled_all' => '「全部」视图不可上传，请进入具体目录',
        'upload_disabled_search' => '搜索模式下不可上传',
        'manage_folders' => '管理目录',
        'items_count' => ':count 项',
        'selected_count' => '已选 :count 项',
    ],

    'types' => [
        'image' => '图片',
        'file' => '文件',
        'video' => '视频',
    ],

    'folders' => [
        'general' => '通用',
        'pets' => '宠物',
        'articles' => '文章',
        'banners' => '横幅',
    ],

    'rich_editor' => [
        'attach_media' => '插入媒体',
        'attach_media_heading' => '从媒体库插入图片',
        'select_media' => '选择图片',
        'insert' => '插入',
    ],

];
