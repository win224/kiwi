<?php
/**
 * Wizard
 *
 * @link      https://aicode.cc/
 * @copyright 管宜尧 <mylxsw@aicode.cc>
 */

namespace App\Listeners;

use App\Components\Search\Search;
use App\Events\DocumentModified;
use App\Notifications\DocumentUpdated;
use App\Repositories\OperationLogs;
use App\Repositories\User;
use Illuminate\Support\Facades\Log;

class DocumentModifiedListener
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param  DocumentModified $event
     *
     * @return void
     */
    public function handle(DocumentModified $event)
    {
        $doc = $event->getDocument();

        // 记录操作日志
        OperationLogs::log(
            \Auth::user()->id,
            'document_updated',
            [
                'username'     => \Auth::user()->name,
                'user_id'      => \Auth::user()->id,
                'project_name' => $doc->project->name,
                'project_id'   => $doc->project_id,
                'doc_title'    => $doc->title,
                'doc_id'       => $doc->id
            ],
            impersonateUser()
        );

        // 发送消息通知相关用户
        $users = User::whereHas('histories', function ($query) use ($doc) {
            $query->where('page_id', $doc->id);
        })->get()->filter(function ($user) use ($doc) {
            // 不通知当前操作用户
            return $user->id != $doc->last_modified_uid;
        });

        if (count($users) > 0) {
            \Notification::send($users, new DocumentUpdated($doc));
        }

        try {
            Search::get()->syncIndex($doc);
        } catch (\Exception $ex) {
            Log::error('update document index failed', ['message' => $ex->getMessage()]);
        }
    }
}
