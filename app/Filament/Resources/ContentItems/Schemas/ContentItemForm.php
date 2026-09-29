<?php

namespace App\Filament\Resources\ContentItems\Schemas;

use App\Filament\Forms\Components\MediaFileUpload;
use App\Filament\Forms\Components\RichTextField;
use App\Support\ContentTypes;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

/**
 * The content-item form.
 *
 * ## Field order is the writing order
 *
 * The first tab is laid out the way a piece is actually written:
 *
 *   1. **Headline** — the `title` field.
 *   2. **Dek / Summary** — the standfirst, shown in listings and search.
 *   3. **Featured image**, with its alt text.
 *   4. **Full article body** — the main writing surface, last and full width.
 *
 * That order previously did not exist. Headline and dek were on an "Essentials"
 * tab with the byline, slug and type selectors, while the body and the image
 * were on a second tab — and there the *body came before the image*, so the
 * two halves of writing a story were split across tabs and the one field that
 * takes the longest was pushed above the one that gives the piece its shape.
 * A journalist opening this form to write was routed through metadata before
 * reaching the text.
 *
 * The type selectors stay at the top of the same tab, in a compact row, rather
 * than moving to a later tab. `content_type` and `source_type` are live and
 * they gate the fields below — the body is only shown for internal content,
 * the gallery only for photo stories. Buried on another tab, a new piece would
 * look like it had no body field at all until you went and changed a dropdown.
 *
 * `author_id` and `slug` moved to "Details". On a single-author site the byline
 * is a constant, and the slug is generated from the headline on create and
 * already populated on edit, so neither earns a place in the writing flow.
 */
class ContentItemForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Content Form')
                    ->tabs([
                        Tab::make('Write')
                            ->schema([
                                // Gate the fields below, so they stay above the
                                // writing surface rather than in a tab of
                                // their own. See the class docblock.
                                Select::make('content_type')
                                    ->options([
                                        'news' => 'News Article',
                                        'investigation' => 'Investigation',
                                        'interview' => 'Interview',
                                        'opinion' => 'Opinion / Analysis',
                                        'video' => 'Video',
                                        'photo_story' => 'Photo Story',
                                        'other' => 'Other Work',
                                    ])
                                    ->required()
                                    ->live()
                                    ->default(fn (): ?string => self::requestedContentType())
                                    // Only clear a *generated* slug on create. Previously
                                    // this fired on edit too, with no guard — so changing
                                    // the content type of a live article wiped its slug.
                                    // Because slug is required, saving then failed until a
                                    // slug was retyped, and any slug entered at that point
                                    // silently republished the piece at a new URL, breaking
                                    // inbound links and search rankings for a metadata tweak.
                                    // Mirrors the guard on `title` below.
                                    ->afterStateUpdated(fn (string $operation, $state, callable $set) => $operation === 'create' ? $set('slug', null) : null)
                                    ->helperText('Determines which fields appear below'),

                                Select::make('source_type')
                                    ->options([
                                        'internal' => 'Internal (hosted on this site)',
                                        'external' => 'External (published elsewhere)',
                                    ])
                                    ->required()
                                    ->live()
                                    ->default('internal')
                                    ->helperText('Internal = full body on this site; External = link out to original'),

                                // ── 1. Headline ───────────────────────────────
                                TextInput::make('title')
                                    ->label('Headline')
                                    ->required()
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn (string $operation, $state, callable $set) => $operation === 'create' ? $set('slug', Str::slug($state)) : null)
                                    ->placeholder('The headline, as it should read in print')
                                    ->columnSpanFull(),

                                // ── 2. Dek / Summary ──────────────────────────
                                Textarea::make('summary')
                                    ->label('Dek / Summary')
                                    ->required()
                                    ->rows(3)
                                    ->columnSpanFull()
                                    ->placeholder('One or two sentences under the headline. Used in listings, search results and as the fallback meta description.'),

                                // ── 3. Image ──────────────────────────────────
                                MediaFileUpload::make('featured_image_media_id')
                                    ->label('Featured Image')
                                    ->image()
                                    ->imageEditor()
                                    ->disk('public')
                                    ->directory('content-items/featured')
                                    ->altTextField('featured_image_alt_text')
                                    ->columnSpanFull()
                                    ->helperText('16:9 ratio recommended for homepage and social sharing'),

                                TextInput::make('featured_image_alt_text')
                                    ->label('Featured Image Alt Text')
                                    ->required(fn (callable $get) => $get('featured_image_media_id') !== null && $get('status') === 'published')
                                    ->placeholder('Describe the featured image for accessibility and SEO')
                                    ->columnSpanFull()
                                    ->helperText('Required when featured image is set and content is published'),

                                // ── 4. Body ───────────────────────────────────
                                RichTextField::make('news_body')
                                    ->label('News Body')
                                    ->columnSpanFull()
                                    ->helperText('A second block of text, edited with this site\'s own editor (no third-party WYSIWYG). Kept separate from the full article body, and sanitized on save.')
                                    ->rules(['nullable', 'string']),

                                RichEditor::make('body')
                                    ->label('Full Article Body')
                                    ->columnSpanFull()
                                    ->visible(fn (callable $get) => $get('source_type') === 'internal')
                                    ->required(fn (callable $get) => $get('source_type') === 'internal')
                                    ->toolbarButtons([
                                        'bold', 'italic', 'strike', 'underline', 'link',
                                        'h2', 'h3', 'blockquote', 'codeBlock',
                                        'bulletList', 'orderedList', 'undo', 'redo',
                                    ])
                                    ->fileAttachmentsDisk('public')
                                    ->fileAttachmentsDirectory('content-items')
                                    ->helperText('The full text of the piece. Only visible for internal content; external pieces link out to the original.'),

                                // ── Type-specific extras ──────────────────────
                                TextInput::make('interviewee_name')
                                    ->label('Interviewee Name')
                                    ->visible(fn (callable $get) => $get('content_type') === 'interview')
                                    ->placeholder('Name of the person interviewed'),

                                TextInput::make('interviewee_title')
                                    ->label('Interviewee Title / Role')
                                    ->visible(fn (callable $get) => $get('content_type') === 'interview')
                                    ->placeholder('e.g., Minister of Health, CEO of XYZ Corp'),

                                TextInput::make('video_url')
                                    ->label('Video URL')
                                    ->visible(fn (callable $get) => $get('content_type') === 'video')
                                    ->url()
                                    ->placeholder('https://www.youtube.com/embed/abc123')
                                    ->helperText('Public https URL of the video (e.g. a YouTube/Vimeo embed link)'),

                                Repeater::make('galleryItems')
                                    ->label('Photo Story Gallery')
                                    ->relationship('galleryItems')
                                    ->schema([
                                        MediaFileUpload::make('media_id')
                                            ->label('Image')
                                            ->image()
                                            ->imageEditor()
                                            ->disk('public')
                                            ->directory('content-items/gallery')
                                            ->altTextField('alt_text')
                                            ->columnSpanFull(),

                                        // Stored on the shared Media row (not the pivot) by
                                        // MediaFileUpload's altTextField() handling.
                                        TextInput::make('alt_text')
                                            ->label('Alt Text')
                                            ->required(fn (callable $get) => $get('media_id') !== null)
                                            ->placeholder('Describe this image for accessibility')
                                            ->columnSpanFull()
                                            ->helperText('Required for accessibility'),

                                        // Stored on the content_item_media pivot row, which is
                                        // what the public photo-story gallery renders.
                                        TextInput::make('caption')
                                            ->label('Caption')
                                            ->placeholder('Optional caption shown under this image')
                                            ->columnSpanFull(),
                                    ])
                                    ->columns(2)
                                    ->columnSpanFull()
                                    ->visible(fn (callable $get) => $get('content_type') === 'photo_story')
                                    ->collapsible()
                                    ->collapsed(false)
                                    // Writes the drag order to content_item_media.sort_order.
                                    ->orderColumn('sort_order')
                                    ->addActionLabel('Add Image to Gallery')
                                    ->itemLabel(fn (array $state): ?string => $state['alt_text'] ?? 'Image'),
                            ])
                            ->columns(2),

                        Tab::make('Details')
                            ->schema([
                                TextInput::make('slug')
                                    ->required()
                                    ->unique(ignoreRecord: true)
                                    ->placeholder('url-friendly-version')
                                    ->helperText('The public address of this piece. Generated from the headline when you first save; edit it only if you are deliberately moving the piece.'),

                                Select::make('author_id')
                                    ->relationship('author', 'name')
                                    ->required()
                                    ->default(auth()->id())
                                    ->helperText('The journalist who wrote this piece')
                                    ->columnSpanFull(),

                                Grid::make(2)
                                    ->schema([
                                        Select::make('category_id')
                                            ->relationship('category', 'name')
                                            ->searchable()
                                            ->preload()
                                            ->label('Category')
                                            ->helperText('Primary topic area'),

                                        Select::make('publication_id')
                                            ->relationship('publication', 'name')
                                            ->searchable()
                                            ->preload()
                                            ->label('Publication')
                                            ->visible(fn (callable $get) => $get('source_type') === 'external')
                                            ->required(fn (callable $get) => $get('source_type') === 'external')
                                            ->helperText('Where this was originally published'),
                                    ])
                                    ->columns(2),

                                TextInput::make('external_url')
                                    ->label('Original Article URL')
                                    ->url()
                                    ->visible(fn (callable $get) => $get('source_type') === 'external')
                                    ->required(fn (callable $get) => $get('source_type') === 'external')
                                    ->columnSpanFull()
                                    ->helperText('The URL where this article was originally published'),

                                Select::make('tags')
                                    ->label('Tags')
                                    ->multiple()
                                    ->searchable()
                                    ->preload()
                                    ->relationship('tags', 'name')
                                    ->createOptionForm([
                                        TextInput::make('name')
                                            ->required()
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(fn (string $operation, $state, callable $set) => $set('slug', Str::slug($state))),
                                        TextInput::make('slug')
                                            ->required()
                                            ->unique(ignoreRecord: true),
                                    ])
                                    ->columnSpanFull()
                                    ->helperText('Keywords for search and filtering'),

                                CheckboxList::make('topics')
                                    ->label('Topics')
                                    ->relationship('topics', 'name')
                                    ->columns(2)
                                    ->columnSpanFull()
                                    ->helperText('Broader themes this piece covers'),

                                // Was a CheckboxList, which rendered one checkbox per
                                // article — 209 of them in a single column on this site,
                                // all fetched and emitted on every edit page load. A
                                // searchable multi-select is the right control at this
                                // scale: the library is unbounded, the current selection is
                                // usually zero or one, and "find the piece I want" needs
                                // search rather than scrolling. Same relationship, so the
                                // stored pivot rows are unaffected.
                                Select::make('relatedContent')
                                    ->label('Related Content')
                                    ->relationship('relatedContent', 'title')
                                    ->multiple()
                                    ->searchable()
                                    ->preload()
                                    ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->title} ({$record->content_type})")
                                    ->helperText('Manually link related pieces to appear at the bottom of this article'),
                            ])
                            ->columns(2),

                        Tab::make('Publishing & SEO')
                            ->schema([
                                Grid::make(2)
                                    ->schema([
                                        Toggle::make('is_featured')
                                            ->label('Featured')
                                            ->helperText('Show on homepage and section highlights'),

                                        Select::make('status')
                                            ->options([
                                                'draft' => 'Draft',
                                                'scheduled' => 'Scheduled',
                                                'published' => 'Published',
                                            ])
                                            ->default('draft')
                                            ->required()
                                            ->helperText('Draft = private; Scheduled = publishes at date; Published = live'),

                                        DateTimePicker::make('published_at')
                                            ->label('Publish Date')
                                            ->visible(fn (callable $get) => in_array($get('status'), ['scheduled', 'published']))
                                            ->default(now())
                                            ->helperText('When this piece goes live (for scheduled items)'),

                                        TextInput::make('reading_time_minutes')
                                            ->label('Reading Time (minutes)')
                                            ->numeric()
                                            ->minValue(1)
                                            ->helperText('Auto-calculated if left blank'),
                                    ])
                                    ->columns(2),

                                Grid::make(2)
                                    ->schema([
                                        TextInput::make('seo_title')
                                            ->label('SEO Title')
                                            ->maxLength(60)
                                            ->placeholder('Leave blank to use article title')
                                            ->helperText('Recommended: 50–60 characters'),

                                        MediaFileUpload::make('seo_og_image_media_id')
                                            ->label('Open Graph Image')
                                            ->image()
                                            ->imageEditor()
                                            ->disk('public')
                                            ->directory('content-items/og')
                                            ->altTextField('seo_og_image_alt_text')
                                            ->columnSpanFull()
                                            ->helperText('1200×630px recommended for social sharing'),

                                        TextInput::make('seo_og_image_alt_text')
                                            ->label('OG Image Alt Text')
                                            ->required(fn (callable $get) => $get('seo_og_image_media_id') !== null && $get('status') === 'published')
                                            ->placeholder('Describe the OG image for social sharing')
                                            ->columnSpanFull()
                                            ->helperText('Required when OG image is set and content is published'),
                                    ])
                                    ->columns(2),

                                Textarea::make('seo_description')
                                    ->label('SEO Description')
                                    ->rows(3)
                                    ->maxLength(160)
                                    ->columnSpanFull()
                                    ->placeholder('Leave blank to use summary')
                                    ->helperText('Recommended: 150–160 characters'),

                                TextInput::make('canonical_url_override')
                                    ->label('Canonical URL Override')
                                    ->url()
                                    ->columnSpanFull()
                                    ->helperText('Only needed for special cases where the canonical URL differs from the article URL'),
                            ])
                            ->columns(2),

                        Tab::make('Advanced')
                            ->schema([
                                KeyValue::make('meta')
                                    ->label('Additional Metadata (JSON)')
                                    ->helperText('For type-specific fields not covered above — stored as JSON')
                                    ->keyLabel('Key')
                                    ->valueLabel('Value')
                                    ->addActionLabel('Add metadata')
                                    ->columnSpanFull(),
                            ])
                            ->columns(2),
                    ])
                    ->columnSpanFull()
                    ->contained(),
            ]);
    }

    /**
     * Content type passed by a quick-create link, e.g.
     * /admin/content-items/create?content_type=interview (ADMIN_PANEL.md §2).
     * Only used as the field's default on create, so stored values are never
     * overwritten when editing.
     */
    private static function requestedContentType(): ?string
    {
        $contentType = request()->query('content_type');

        return is_string($contentType) && array_key_exists($contentType, ContentTypes::TYPE_LABELS)
            ? $contentType
            : null;
    }
}
