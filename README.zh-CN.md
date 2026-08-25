# Filament Media Library

<p align="center">
    <a href="LICENSE.md"><img src="https://img.shields.io/badge/license-MIT-blue.svg" alt="MIT License"></a>
    <img src="https://img.shields.io/badge/filament-v5-orange.svg" alt="Filament v5">
    <img src="https://img.shields.io/badge/laravel-v12-red.svg" alt="Laravel 12">
    <img src="https://img.shields.io/badge/php-%5E8.2-8892BF.svg" alt="PHP ^8.2">
</p>

一个开箱即用的 **Filament v5 媒体库插件**：中心化的平台媒体库管理 + 文件夹体系 + 可在任意表单中使用的 `MediaPicker` 选择器（文件夹浏览 / 搜索 / 弹窗内上传）。

与 [spatie/laravel-medialibrary](https://spatie.be/docs/laravel-medialibrary)（底层存储库）和官方 [Filament spatie 插件](https://filamentphp.com/plugins/spatie-laravel-media-library)（模型表单字段）不同，本包提供的是**管理后台里的完整媒体库**：

- **媒体库资源** —— 上传、预览、整理、软删除、恢复、彻底删除
- **媒体目录资源** —— 两级目录层级，自动映射存储路径
- **`MediaPicker` 表单字段** —— 任意 Filament 表单可用的弹窗选择器（目录 + 搜索 + 内联上传）
- **模型关系绑定** —— `->relationship()` 单选（BelongsTo）与多选（BelongsToMany）自动加载/保存
- 事件、自定义属性、缩略图 Provider、i18n、零构建依赖

## 功能特性

- **媒体库管理资源**：拖拽上传（内置图片编辑器，可裁剪/缩放）、实时预览、缩略图/目录/类型徽章表格、目录与类型筛选、软删除（回收站筛选、恢复、彻底删除）
- **媒体目录管理资源**：两级层级、slug 存储路径（`media/library/{大类}/{代号}/…`）、排序、按目录启停
- **`MediaPicker` 表单字段**：
  - 文件夹导航 + 面包屑 + 「上级目录」
  - **防抖搜索**（按文件名 / 备注）
  - 上传到当前目录（弹窗内直接上传，带图片编辑）
  - **单选 / 多选**（多选时网格变复选框，显示已选计数）
  - **模型关系绑定**：单选 `BelongsTo` 自动 `associate`；多选 `BelongsToMany` 自动 `sync`，可带排序中间列
- **自定义属性**：每条媒体记录的键值元数据（JSON 列，KeyValue 字段编辑）
- **事件**：`MediaUploaded` / `MediaDeleted`（带 `force` 标志）/ `MediaRestored`
- **ThumbnailProvider 抽象**：可插拔的缩略图 URL 生成（内置腾讯 COS `imageMogr2`，默认本地直出）
- **i18n**：内置英文 + 简体中文，所有界面文案可翻译
- **零构建依赖**：选择器 CSS 全部内联，宿主无需 Vite/Tailwind 主题

## 环境要求

| 组件 | 版本 |
|---|---|
| PHP | ^8.2 |
| Laravel | ^12.0 |
| Filament | ^5.3 |

## 安装

```bash
composer require xpier/filament-media-library
```

包会自动注册 ServiceProvider。迁移文件随包自动加载，直接执行：

```bash
php artisan migrate
```

在面板 Provider（`app/Providers/Filament/AdminPanelProvider.php`）注册插件：

```php
use Xpier\FilamentMediaLibrary\Filament\MediaLibraryPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        // ...
        ->plugins([
            MediaLibraryPlugin::make()
                ->navigationGroup('内容管理'), // 可选，见「配置」
        ]);
}
```

完成。侧边栏即可看到**媒体库**和**媒体目录**两个入口。

## 在 Filament 表单中使用 MediaPicker

### 1. 基础用法（存 id）

```php
use Xpier\FilamentMediaLibrary\Components\MediaPicker;

public static function form(Schema $schema): Schema
{
    return $schema->components([
        MediaPicker::make('cover_image')
            ->label('封面图')
            ->module('articles')   // 上传时的默认目录代号
            ->mediaType('image'),  // 'image' | 'file' | 'video' | null（全部）
    ]);
}
```

默认 `storeMode('id')`：字段存 `media_library.id`（整数）。读取时再解析 URL：

```php
use Xpier\FilamentMediaLibrary\Models\MediaLibrary;

$media = MediaLibrary::find($post->cover_image);
$url = $media?->url; // 磁盘完整 URL
```

### 2. 存 URL 字符串（兼容旧数据）

```php
MediaPicker::make('cover_image')
    ->storeMode('url'), // 存解析后的 URL 字符串
```

适合历史遗留的 URL/path 列，与媒体库解耦。

### 3. 单选关联模型（BelongsTo）—— 推荐

```php
// 表单里：字段名任意，relationship() 指定模型上的关系
MediaPicker::make('cover_image')
    ->relationship('featuredImage'),  // 单选 + BelongsTo
```

```php
// 模型侧（post 表需要 featured_image_id 外键列）
use Xpier\FilamentMediaLibrary\Models\MediaLibrary;

public function featuredImage(): BelongsTo
{
    return $this->belongsTo(MediaLibrary::class, 'featured_image_id');
}
```

保存时自动 `associate`（无选中则置空），编辑页打开时自动从关系加载。字段本身不写任何列（`dehydrated(false)`），外键由关系维护。

读取：

```php
$post->featuredImage?->url;
```

### 4. 多选关联模型（BelongsToMany）—— 相册/图集

```php
// 表单里
MediaPicker::make('gallery')
    ->multiple()
    ->relationship('galleryMedia')
    ->orderColumn('order'), // 可选：中间表排序列，自动按选择顺序写入
```

```php
// 模型侧
use Xpier\FilamentMediaLibrary\Models\MediaLibrary;

public function galleryMedia(): BelongsToMany
{
    return $this->belongsToMany(MediaLibrary::class, 'post_media', 'post_id', 'media_id')
        ->withPivot('order')
        ->orderBy('order');
}
```

保存时自动 `sync`（新增/移除/排序一并处理），编辑页打开时自动加载已有选择。

读取：

```php
$urls = $post->galleryMedia->pluck('url'); // 按 order 排序的 URL 列表
```

### 5. 多选但不用关系（存 JSON 列）

```php
MediaPicker::make('cover_images')
    ->multiple(), // 无 relationship：state 为整数数组，直接写入列
```

列需要 JSON 类型并在模型 cast：

```php
protected function casts(): array
{
    return ['cover_images' => 'array'];
}
```

### 6. 媒体类型过滤

| `mediaType()` | 过滤器 | 上传接受类型 |
|---|---|---|
| `'image'` | 仅图片 | `image/jpeg, png, webp, gif` |
| `'file'` | 仅文件 | — |
| `'video'` | 仅视频 | `video/mp4, webm, quicktime` |
| `null` | 全部 | 图片 + 视频 |

### 7. 定制媒体查询

```php
MediaPicker::make('cover_image')
    ->modifyMediaQueryUsing(function (Builder $query) {
        $query->where('size', '>', 1024);
    }),
```

### 8. 字段级磁盘与可见性

默认上传到全局配置的磁盘（`MEDIA_DISK`）与可见性（`MEDIA_VISIBILITY`）。个别字段可覆盖：

```php
MediaPicker::make('cover_image')
    ->disk('private_s3')        // 该字段的上传写入指定磁盘
    ->visibility('private'),    // 该字段的上传可见性（public | private）
```

### 9. 私有写、公有读（CDN / 公开域名）

典型场景：文件存储在**私有**磁盘（或私有 bucket），但对外通过 **CDN / 公开域名**访问。默认配置下 `Storage::url()` 返回的私有地址会 403。三种方式：

**方式一：按磁盘映射公开域名（多磁盘推荐）**

发布配置文件后，在 `config/filament-media-library.php` 中按磁盘配置：

```php
'public_urls' => [
    'private_s3' => 'https://cdn.example.com',
    'r2' => 'https://pub-xxxx.r2.dev',
],
```

每个磁盘的媒体 `url` 属性自动解析为 `{映射域名}/{存储路径}`，与底层磁盘的可见性无关 —— 文件留在私有磁盘，对外走各自的公开域名。

**方式二：单一公开域名（最简单）**

```env
MEDIA_PUBLIC_URL=https://cdn.example.com
```

未在 `public_urls` 中映射的磁盘都使用该前缀。

> 提示：如果磁盘本身已配置了公开地址（如 S3 的 `AWS_URL` 或 R2 的公开 bucket 域名），`Storage::url()` 已经返回公开 URL，无需额外配置。

**方式三：自定义 URL 解析器（最高优先级）**

```php
// config/filament-media-library.php
'url_resolver' => \App\Support\MyUrlResolver::class,
```

```php
use Xpier\FilamentMediaLibrary\Support\MediaUrlResolver;

class MyUrlResolver implements MediaUrlResolver
{
    // 返回 null 时回退到默认 Storage::disk()->url() 行为
    public function url(string $disk, string $path): ?string
    {
        return rtrim(config('cdn.base_url'), '/').'/'.ltrim($path, '/');
    }
}
```

也可以用签名 URL（临时 URL）实现私有读：

```php
public function url(string $disk, string $path): ?string
{
    return Storage::disk($disk)->temporaryUrl($path, now()->addMinutes(60));
}
```

**完整解析链**：`url_resolver` 类 → `public_urls[磁盘]` → `public_url` 全局默认 → `Storage::url()`。

## 在 RichEditor 中插入媒体

基于 Filament 自带的 RichEditor 插件机制（`RichContentPlugin`），不引入任何第三方编辑器库：

```php
use Xpier\FilamentMediaLibrary\Filament\RichEditor\AttachMediaPlugin;
use Filament\Forms\Components\RichEditor;

RichEditor::make('content')
    ->plugins([
        AttachMediaPlugin::make(),
    ]),
```

工具栏出现「插入媒体」按钮 → 打开弹窗（内含 MediaPicker：目录浏览 / 搜索 / 上传）→ 选中图片插入到光标处（Tiptap image 节点，自动带上 `alt_text`）。已选中图片时按钮高亮。

### 10. 上传安全

`acceptedFileTypes()` 只是前端提示（FilePond），包在 `AdminMediaService::storeUpload()` 里做了**服务端强制校验**：

- **扩展名白名单**（按媒体类型），基于 MIME 检测结果落盘 —— 名为 `evil.php` 的图片内容会以 `.png` 落盘，客户端扩展名不参与存储
- **大小上限**：`MEDIA_MAX_SIZE`（默认 20 MB），超限抛异常
- 两处 FileUpload（后台上传 + 弹窗上传）同步加了前端 `maxSize` 提示

## 媒体库管理资源

`/admin/media-library`：

- 上传（图片编辑器裁剪/缩放）、选择目录与类型、填写备注与自定义属性
- 表格：缩略图、文件名（可搜索）、目录徽章、类型徽章、大小、上传时间
- 筛选：目录、类型、已删除
- 删除行为由配置控制（`MEDIA_DELETE_MODE`）：
  - `soft`（默认）：删除进回收站，Trashed 筛选可恢复；物理文件默认同时移除（公开 URL 立即失效），设 `MEDIA_DELETE_FILE_ON_DELETE=false` 可保留文件以便恢复后 URL 仍有效
  - `physical`：删除即删记录 + 物理文件，不经过回收站

## 媒体目录管理资源

`/admin/media-folders`：

| 字段 | 说明 |
|---|---|
| 目录名称 | 显示名 |
| 代号 | 英文短名，参与存储路径 |
| 上级目录 | 可选，子目录仅支持挂在一级大类下 |
| 排序 | 展示顺序 |
| 启用 | 控制选择器中是否可见 |

存储路径自动派生：`general` → `media/library/general/…`；`pets` 下的子目录 `dogs` → `media/library/pets/dogs/…`。

## 事件

在 ServiceProvider 或 EventServiceProvider 中监听：

```php
use Xpier\FilamentMediaLibrary\Events\MediaUploaded;

Event::listen(function (MediaUploaded $event): void {
    $media = $event->media;
    // 例如：通知审核队列、清 CDN 缓存、同步搜索索引
});
```

| 事件 | 参数 | 触发时机 |
|---|---|---|
| `MediaUploaded` | `MediaLibrary $media` | 记录创建（选择器上传、后台上传、程序化创建） |
| `MediaDeleted` | `MediaLibrary $media, bool $force` | 记录删除；`$force = true` 表示物理文件已移除 |
| `MediaRestored` | `MediaLibrary $media` | 软删除记录恢复 |

## 配置

发布配置文件：

```bash
php artisan vendor:publish --tag="filament-media-library-config"
```

| 键 | 环境变量 | 默认值 | 说明 |
|---|---|---|---|
| `disk` | `MEDIA_DISK` | 有 `AWS_BUCKET` 则 `s3`，否则 `public` | 存储磁盘 |
| `directory` | `MEDIA_DIRECTORY` | `media` | 存储根目录 |
| `visibility` | `MEDIA_VISIBILITY` | `public` | 文件可见性 |
| `max_size` | `MEDIA_MAX_SIZE` | `20` | 上传大小上限（MB，服务端强制校验） |
| `delete_mode` | `MEDIA_DELETE_MODE` | `soft` | 删除模式：`soft`（回收站可恢复）/ `physical`（立即删记录+文件） |
| `delete_file_on_delete` | `MEDIA_DELETE_FILE_ON_DELETE` | `true` | 软删除时是否移除物理文件 |
| `image_process` | `MEDIA_COS_IMAGE_PROCESS` | `true` | COS 图片处理开关 |
| `thumbnail_provider` | `MEDIA_THUMBNAIL_PROVIDER` | `LocalThumbnailProvider` | 缩略图 Provider 类 |
| `public_urls` | 发布后手动填写 | `[]` | 按磁盘映射公开域名（磁盘 → CDN 前缀） |
| `public_url` | `MEDIA_PUBLIC_URL` | *(空)* | 未映射磁盘的全局公开 URL 前缀 |
| `url_resolver` | `MEDIA_URL_RESOLVER` | *(空)* | 自定义 `MediaUrlResolver` 类，最高优先级 |
| `navigation_group` | `MEDIA_NAVIGATION_GROUP` | *(语言包)* | 导航分组；`null` 时回退到语言包 |
| `navigation_sort` | `MEDIA_NAVIGATION_SORT` | `5` | 导航排序 |
| `navigation_badge` | `MEDIA_NAVIGATION_BADGE` | `true` | 导航显示媒体数量徽章 |
| `default_module` | `MEDIA_DEFAULT_MODULE` | `general` | 默认上传目录 |
| `folder_presets` | — | `[]` | 预留：目录预置 |

**导航分组解析顺序**：插件方法参数 → 环境变量 → 配置文件 → 语言包（中文「系统管理」/ 英文 "System"）。

## 缩略图 Provider

实现 `Xpier\FilamentMediaLibrary\Support\ThumbnailProvider` 接口控制缩略图生成：

```php
interface ThumbnailProvider
{
    public function thumbnail(?string $url, int $maxEdge = 400, int $quality = 75): ?string;
}
```

| Provider | 行为 |
|---|---|
| `LocalThumbnailProvider`（默认） | 原样返回 URL —— 适用于 R2、本地磁盘等无服务端图片处理的场景 |
| `CosThumbnailProvider` | 腾讯 COS `imageMogr2` 缩略图 |

```env
MEDIA_THUMBNAIL_PROVIDER=Xpier\FilamentMediaLibrary\Support\Providers\CosThumbnailProvider
```

自定义 Provider 通过容器绑定（单例，无性能开销）：

```php
$this->app->bind(
    \Xpier\FilamentMediaLibrary\Support\ThumbnailProvider::class,
    \App\Support\MyThumbnailProvider::class,
);
```

## 翻译

内置 `en` 与 `zh_CN`。发布后按需修改：

```bash
php artisan vendor:publish --tag="filament-media-library-lang"
```

文件位于 `lang/vendor/filament-media-library/{locale}/media-library.php`。新增语言：创建对应 locale 目录下的同名文件即可。

## 视图

```bash
php artisan vendor:publish --tag="filament-media-library-views"
```

| 视图 | 用途 |
|---|---|
| `media-picker.blade.php` | 字段外壳：预览、移除、打开选择器 |
| `media-picker-grid.blade.php` | 弹窗浏览器：工具栏、面包屑、搜索、网格（CSS 全部内联） |

## 测试

```bash
composer install        # 安装 testbench / phpunit
vendor/bin/phpunit      # 运行测试套件
```

52 个测试覆盖：模型（软删除文件行为、物理删除模式、事件派发、URL 解析、目录路径与嵌套校验）、上传服务（**服务端扩展名白名单与大小限制**、磁盘/可见性/目录规范化）、URL 解析器（按磁盘映射、全局回退、自定义类）、缩略图 Provider（本地直出、COS imageMogr2）、MediaPicker 组件（多选、relationship 钩子注册、字段级磁盘/可见性、选中媒体解析、禁用目录回退）。

## 与其他方案对比

### vs spatie/laravel-medialibrary

Spatie 是**底层存储层**——把文件挂到你的 Eloquent 模型上（`$model->addMedia(...)->toMediaCollection('gallery')`），负责 conversions、响应式图片等，与 UI 无关。本包是**中心化媒体库的 Filament 管理 UI**，二者解决的问题不同：

| 能力 | spatie | 本包 |
|---|---|---|
| 媒体挂载到模型 | ✅ | 通过存 `id`/`url` 或 `->relationship()` |
| 命名集合 | ✅ | 用目录层级替代 |
| conversions / 响应式图片 | ✅（磁盘派生文件） | 按需通过 `ThumbnailProvider` |
| 后台管理 UI | 仅 Pro 版 | ✅ 内置 |
| 目录浏览选择器 | 仅 Pro 版 | ✅ 内置 |
| 事件 | ✅ | ✅（3 个核心事件） |
| i18n | — | ✅ 中英双语 |

**怎么选**：模型级媒体 + 派生图 → spatie（+ 官方插件）；中心化、分目录的平台媒体库 + 选择器 → 本包。两者可以共存。

### vs 官方 filament/spatie-laravel-media-library-plugin

该插件只是 spatie 的表单字段桥接（`SpatieMediaLibraryFileUpload`），没有管理资源、没有共享媒体库。本包是反面：管理资源 + 共享媒体库 + 选择器。不需要 spatie 的 conversions 时，本包是更轻的选择。

### vs awcodes/filament-curator

Curator 是最接近的竞品 —— 媒体管理器 + 选择器 + 关系绑定 + 多选 + Glide 签名 URL。

| 能力 | curator | 本包 |
|---|---|---|
| 管理资源 | ✅ | ✅ |
| 目录层级 | ❌（仅路径生成器） | ✅ 两级目录 |
| 弹窗选择 + 搜索 | ✅ | ✅ |
| 软删除 | ❌ | ✅ |
| `->relationship()` 选择器 | ✅ | ✅ |
| `->multiple()` 多选 | ✅ | ✅（可带排序） |
| RichEditor 附件按钮 | ✅ | ❌ *规划中* |
| 图片编辑器（裁剪/缩放） | ✅ | ✅（FileUpload 内置） |
| 字段级多磁盘 | ✅ | ✅ `->disk()` |
| 签名 URL | ✅ Glide | ✅ 自定义 `MediaUrlResolver` |
| 自定义属性 | ❌ | ✅ |
| 中文 i18n | ❌ | ✅ |

## Roadmap

- 测试套件 + CI（Pint + Pest）已就绪 ✅ 下一步接 CI（GitHub Actions）

## 发布流程

本包通过 Git tag 管理版本（`v1.x.x`）：

1. 打 tag：`git tag v1.1.0 && git push origin --tags`
2. 若已在 Packagist 注册仓库（GitHub App 自动同步），tag 会自动发布；否则到 packagist.org 手动点 Update

## License

MIT —— 见 [LICENSE.md](LICENSE.md)。
