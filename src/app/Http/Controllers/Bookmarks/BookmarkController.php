<?php


namespace App\Http\Controllers\Bookmarks;

use App\Bookmark\UseCase\CreateBookmarkUseCase;
use App\Bookmark\UseCase\ShowBookmarkListCategoryPageUseCase;
use App\Bookmark\UseCase\ShowBookmarkListPageUseCase;
use App\Bookmark\UseCase\UpdateBookmarkUseCase;
use App\Http\Requests\CreateBookmarkRequest;
use App\Http\Requests\UpdateBookmarkRequest;
use App\Http\Controllers\Controller;
use App\Lib\LinkPreview\LinkPreview;
use App\Models\Bookmark;
use App\Models\BookmarkCategory;
use App\Models\User;
use Artesaos\SEOTools\Facades\SEOTools;
use Dusterio\LinkPreview\Client;
use Dusterio\LinkPreview\Exceptions\UnknownParserException;
use Exception;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use tests\Feature\Bookmarks\ShowBookmarkListCategoryPageTest;

class BookmarkController extends Controller
{
    /**
     * ブックマーク一覧画面
     * @return Application|Factory|View
     */
    public function list(Request $request, ShowBookmarkListPageUseCase $useCase)
    {
        return view('page.bookmark_list.index', [
            'h1' => 'ブックマーク一覧'] + $useCase->handle()
        );
    }

    /**
     * カテゴリ別ブックマーク一覧
     *
     * @param Request $request
     * @param ShowBookmarkListCategoryPageUseCase
     * @return Application|Factory|View
     */
    public function listCategory(Request $request, ShowBookmarkListCategoryPageUseCase $useCase)
    {
        if (!is_numeric($request->category_id)) abort(404);
        return view(
            'page.bookmark_list.index', 
            $useCase->handle($request->category_id)
        );
    }

    /**
     * ブックマーク作成フォームの表示
     * @return Application|Factory|View
     */
    public function showCreateForm()
    {
        if (Auth::id() === null) {
            return redirect('/login');
        }

        SEOTools::setTitle('ブックマーク作成');

        $master_categories = BookmarkCategory::query()->oldest('id')->get();

        return view('page.bookmark_create.index', [
            'master_categories' => $master_categories,
        ]);
    }

    /**
     * ブックマーク作成処理
     * @param CreateBookmarkRequest $request
     * @param CreateBookmarkUseCase $useCase
     * @return Application|\Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function create(CreateBookmarkRequest $request, CreateBookmarkUseCase $useCase)
    {
        $useCase->handle($request->url, $request->category, $request->comment);

        // 暫定的に成功時は一覧ページへ
        return redirect('/bookmarks', 302);
    }

    /**
     * 編集画面の表示
     * 未ログインであればログインページへ
     * 存在しないブックマークの編集画面なら表示しない
     * 本人のブックマークでなければ403で返す
     *
     * @param Request $request
     * @param int $id
     * @return Application|Factory|\Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector|View
     */
    public function showEditForm(Request $request, int $id)
    {
        if (Auth::guest()) {
            // @note ここの処理はユーザープロフィールでも使われている
            return redirect('/login');
        }

        SEOTools::setTitle('ブックマーク編集');

        $bookmark = Bookmark::query()->findOrFail($id);
        if ($bookmark->user_id !== Auth::id()) {
            abort(403);
        }

        $master_categories = BookmarkCategory::query()->withCount('bookmarks')->orderBy('bookmarks_count', 'desc')->orderBy('id')->take(10)->get();

        return view('page.bookmark_edit.index', [
            'user' => Auth::user(),
            'bookmark' => $bookmark,
            'master_categories' => $master_categories,
        ]);
    }

    /**
     * ブックマーク更新
     *
     * @param UpdateBookmarkRequest $request
     * @param int $id
     * @param UpdateBookmarkUseCase $useCase
     * @return Application|\Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function update(UpdateBookmarkRequest $request, int $id, UpdateBookmarkUseCase $useCase)
    {
        $useCase->handle($id, $request->category, $request->comment);
        
        // 成功時は一覧ページへ
        return redirect('/bookmarks', 302);
    }

    /**
     * ブックマーク削除
     * 公開後24時間経過したものは削除できない
     * 本人以外のブックマークは削除できない
     *
     * @param int $id
     * @return Application|\Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     * @throws ValidationException
     */
    public function delete(int $id)
    {
        if (Auth::guest()) {
            // @note ここの処理はユーザープロフィールでも使われている
            return redirect('/login');
        }

        $model = Bookmark::query()->findOrFail($id);

        if ($model->can_not_delete_or_edit) {
            throw ValidationException::withMessages([
                'can_delete' => 'ブックマーク後24時間経過したものは削除できません'
            ]);
        }

        if ($model->user_id !== Auth::id()) {
            abort(403);
        }

        $model->delete();

        // 暫定的に成功時はプロフィールページへ
        return redirect('/user/profile', 302);
    }
}
