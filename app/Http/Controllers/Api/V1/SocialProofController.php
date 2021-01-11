<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\AuthService;
use App\SocialProof;
use App\Traits\ResponseMaker;
use Illuminate\Http\Request;

class SocialProofController extends Controller
{
    use ResponseMaker;

    /**
     * @param Request $request
     * @param AuthService $authService
     * @return \Illuminate\Http\Response|\Laravel\Lumen\Http\ResponseFactory
     */
    public function all(Request $request, AuthService $authService)
    {
        $proofs = SocialProof::where('visible', true)
            ->orderBy('updated_at', 'desc')
            ->paginate(10)
            ->toArray();
        $data = $proofs['data'];
        $userIds = [];
        foreach ($data as $proof) {
            $userIds[] = $proof['user_id'];
        }
        $users = $this->getUsers($userIds, $authService);
        if ($users) {
            $newData = [];
            foreach ($data as $proof) {
                $name = $users[$proof['user_id']]['name'] ??  $users[$proof['user_id']]['masked_phone'];
                $newData[] = [
                    'name' => $name,
                    'image' => $users[$proof['user_id']]['image_url'],
                    'play_count' => $proof['play_count'],
                    'won_amount' => $proof['won_amount'],
                    'currency' => $proof['currency'],
                    'comment' => $proof['comment'],
                ];
            }
            $proofs['data'] = $newData;
            return $this->success($proofs);
        }
        return $this->failMessage('Content not found.', 404);
    }

    /**
     * @param array $userIds
     * @param AuthService $authService
     * @return array
     */
    private function getUsers(array $userIds, AuthService $authService)
    {
        if ($userIds) {
            $usersResponse = $authService->getUsersById($userIds);
            if ($usersResponse['status'] == 200) {
                $users = $usersResponse['data'];
                $usersByUserId = [];
                foreach ($users as $user) {
                    $usersByUserId[$user['id']] = $user;
                }
                return $usersByUserId;
            } else {
                return [];
            }
        }
        return [];
    }
}
