<?php

namespace App\Bookmark\UseCase;

use App\Models\BookmarkCategory;
use App\Models\Bookmark;
use App\Models\User;
use Artesaos\SEOTools\Facades\SEOTools;

final class ShowBookmarkListCategoryPageUseCase
{
    /**
     * カテゴリが存在しないIDが指定された場合404 (findOrFail)
     *
     * title, descriptionにはカテゴリ名とカテゴリのブックマーク投稿数を含める
     *
     * 表示する内容は普通の一覧と同様
     * しかし、カテゴリに関しては現在のページのカテゴリを除いて表示する
     * @param int $category_id
     * @return array
     */
    public function handle(int $category_id):array
    {
        $category = BookmarkCategory::query()->findOrFail($category_id);

        SEOTools::setTitle("{$category->display_name}のブックマーク一覧");
        SEOTools::setDescription("{$category->display_name}に特化したブックマーク一覧です。みんなが投稿した{$category->display_name}のブックマークが投稿順に並んでいます。全部で{$category->bookmarks->count()}件のブックマークが投稿されています");

        $bookmarks = Bookmark::query()->with(['category', 'user'])->where('category_id', '=', $category_id)->latest('id')->paginate(10);

        // 自身のページのカテゴリを表示しても意味がないのでそれ以外のカテゴリで多い順に表示する
        $top_categories = BookmarkCategory::query()->withCount('bookmarks')->orderBy('bookmarks_count', 'desc')->orderBy('id')->where('id', '<>', $category_id)->take(10)->get();

        $top_users = User::query()->withCount('bookmarks')->orderBy('bookmarks_count', 'desc')->take(10)->get();

        return [
            'h1' => "{$category->display_name}のブックマーク一覧",
            'bookmarks' => $bookmarks,
            'top_categories' => $top_categories,
            'top_users' => $top_users,
        ];
    }
}