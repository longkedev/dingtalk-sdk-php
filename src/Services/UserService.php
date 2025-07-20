<?php

declare(strict_types=1);

namespace DingTalk\Services;

/**
 * 用户管理服务
 * 
 * 提供用户相关的API操作
 */
class UserService extends BaseService
{
    /**
     * 获取用户详情
     */
    public function getUser(string $userId): array
    {
        $this->validateRequired(['userId' => $userId], ['userId']);
        
        $cacheKey = $this->buildCacheKey('user', ['userId' => $userId]);
        
        return $this->remember($cacheKey, function () use ($userId) {
            $apiVersion = $this->config->get('api_version', 'v1');
            
            if ($apiVersion === 'v2') {
                return $this->getV2User($userId);
            }
            
            return $this->getV1User($userId);
        }, 1800); // 缓存30分钟
    }

    /**
     * 通过手机号获取用户ID
     */
    public function getByMobile(string $mobile): array
    {
        $this->validateRequired(['mobile' => $mobile], ['mobile']);
        
        $cacheKey = $this->buildCacheKey('user_by_mobile', ['mobile' => $mobile]);
        
        return $this->remember($cacheKey, function () use ($mobile) {
            $apiVersion = $this->config->get('api_version', 'v1');
            
            if ($apiVersion === 'v2') {
                return $this->getV2UserByMobile($mobile);
            }
            
            return $this->getV1UserByMobile($mobile);
        }, 1800);
    }

    /**
     * 通过unionId获取用户ID
     */
    public function getByUnionId(string $unionId): array
    {
        $this->validateRequired(['unionId' => $unionId], ['unionId']);
        
        $cacheKey = $this->buildCacheKey('user_by_unionid', ['unionId' => $unionId]);
        
        return $this->remember($cacheKey, function () use ($unionId) {
            $apiVersion = $this->config->get('api_version', 'v1');
            
            if ($apiVersion === 'v2') {
                return $this->getV2UserByUnionId($unionId);
            }
            
            return $this->getV1UserByUnionId($unionId);
        }, 1800);
    }

    /**
     * 获取部门用户列表
     */
    public function listByDepartment(int $departmentId, int $offset = 0, int $size = 100): array
    {
        $this->validateRequired(['departmentId' => $departmentId], ['departmentId']);
        
        $params = [
            'dept_id' => $departmentId,
            'offset' => $offset,
            'size' => min($size, 100), // 限制最大100
        ];
        
        $apiVersion = $this->config->get('api_version', 'v1');
        
        if ($apiVersion === 'v2') {
            return $this->getV2DepartmentUsers($params);
        }
        
        return $this->getV1DepartmentUsers($params);
    }

    /**
     * 获取部门用户详情列表
     */
    public function listDetailsByDepartment(int $departmentId, int $offset = 0, int $size = 100): array
    {
        $this->validateRequired(['departmentId' => $departmentId], ['departmentId']);
        
        $params = [
            'dept_id' => $departmentId,
            'offset' => $offset,
            'size' => min($size, 100),
        ];
        
        $apiVersion = $this->config->get('api_version', 'v1');
        
        if ($apiVersion === 'v2') {
            return $this->getV2DepartmentUserDetails($params);
        }
        
        return $this->getV1DepartmentUserDetails($params);
    }

    /**
     * 创建用户
     */
    public function create(array $userData): array
    {
        $required = ['name', 'mobile'];
        $this->validateRequired($userData, $required);
        
        $apiVersion = $this->config->get('api_version', 'v1');
        
        if ($apiVersion === 'v2') {
            return $this->createV2User($userData);
        }
        
        return $this->createV1User($userData);
    }

    /**
     * 更新用户
     */
    public function update(string $userId, array $userData): array
    {
        $this->validateRequired(['userId' => $userId] + $userData, ['userId']);
        
        $userData['userid'] = $userId;
        
        $apiVersion = $this->config->get('api_version', 'v1');
        
        if ($apiVersion === 'v2') {
            $result = $this->updateV2User($userData);
        } else {
            $result = $this->updateV1User($userData);
        }
        
        // 清除缓存
        $this->forgetCache($this->buildCacheKey('user', ['userId' => $userId]));
        
        return $result;
    }

    /**
     * 删除用户
     */
    public function deleteUser(string $userId): array
    {
        $this->validateRequired(['userId' => $userId], ['userId']);
        
        $apiVersion = $this->config->get('api_version', 'v1');
        
        if ($apiVersion === 'v2') {
            $result = $this->deleteV2User($userId);
        } else {
            $result = $this->deleteV1User($userId);
        }
        
        // 清除缓存
        $this->forgetCache($this->buildCacheKey('user', ['userId' => $userId]));
        
        return $result;
    }

    /**
     * 批量获取用户详情
     */
    public function batchGet(array $userIds): array
    {
        if (empty($userIds)) {
            return [];
        }
        
        // 限制批量大小
        $userIds = array_slice($userIds, 0, 100);
        
        return $this->batch($userIds, function ($batch) {
            $users = [];
            foreach ($batch as $userId) {
                try {
                    $users[] = $this->getUser($userId);
                } catch (\Exception $e) {
                    $this->logger->warning('Failed to get user', [
                        'user_id' => $userId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
            return $users;
        }, 20);
    }

    /**
     * 获取所有部门用户（分页迭代）
     */
    public function getAllByDepartment(int $departmentId): \Generator
    {
        return $this->paginate(function ($params) {
            return $this->listByDepartment(
                $params['dept_id'],
                $params['offset'],
                $params['size']
            );
        }, ['dept_id' => $departmentId]);
    }

    /**
     * V1版本：获取用户详情
     */
    private function getV1User(string $userId): array
    {
        $accessToken = $this->auth->getAccessToken();
        return $this->get('/user/get', [
            'access_token' => $accessToken,
            'userid' => $userId,
        ]);
    }

    /**
     * V2版本：获取用户详情
     */
    private function getV2User(string $userId): array
    {
        return $this->get("/v1.0/contact/users/{$userId}");
    }

    /**
     * V1版本：通过手机号获取用户ID
     */
    private function getV1UserByMobile(string $mobile): array
    {
        $accessToken = $this->auth->getAccessToken();
        return $this->get('/user/get_by_mobile', [
            'access_token' => $accessToken,
            'mobile' => $mobile,
        ]);
    }

    /**
     * V2版本：通过手机号获取用户ID
     */
    private function getV2UserByMobile(string $mobile): array
    {
        return $this->post('/v1.0/contact/users/getByMobile', [
            'mobile' => $mobile,
        ]);
    }

    /**
     * V1版本：通过unionId获取用户ID
     */
    private function getV1UserByUnionId(string $unionId): array
    {
        $accessToken = $this->auth->getAccessToken();
        return $this->get('/user/getUseridByUnionid', [
            'access_token' => $accessToken,
            'unionid' => $unionId,
        ]);
    }

    /**
     * V2版本：通过unionId获取用户ID
     */
    private function getV2UserByUnionId(string $unionId): array
    {
        return $this->post('/v1.0/contact/users/getByUnionId', [
            'unionId' => $unionId,
        ]);
    }

    /**
     * V1版本：获取部门用户列表
     */
    private function getV1DepartmentUsers(array $params): array
    {
        $accessToken = $this->auth->getAccessToken();
        return $this->get('/user/simplelist', array_merge($params, [
            'access_token' => $accessToken,
        ]));
    }

    /**
     * V2版本：获取部门用户列表
     */
    private function getV2DepartmentUsers(array $params): array
    {
        return $this->post('/v1.0/contact/departments/users', [
            'deptId' => $params['dept_id'],
            'cursor' => $params['offset'],
            'size' => $params['size'],
        ]);
    }

    /**
     * V1版本：获取部门用户详情列表
     */
    private function getV1DepartmentUserDetails(array $params): array
    {
        $accessToken = $this->auth->getAccessToken();
        return $this->get('/user/listbypage', array_merge($params, [
            'access_token' => $accessToken,
        ]));
    }

    /**
     * V2版本：获取部门用户详情列表
     */
    private function getV2DepartmentUserDetails(array $params): array
    {
        return $this->post('/v1.0/contact/departments/users/query', [
            'deptId' => $params['dept_id'],
            'cursor' => $params['offset'],
            'size' => $params['size'],
        ]);
    }

    /**
     * V1版本：创建用户
     */
    private function createV1User(array $userData): array
    {
        $accessToken = $this->auth->getAccessToken();
        return $this->post('/user/create', $userData, [
            'access_token' => $accessToken,
        ]);
    }

    /**
     * V2版本：创建用户
     */
    private function createV2User(array $userData): array
    {
        return $this->post('/v1.0/contact/users', $userData);
    }

    /**
     * V1版本：更新用户
     */
    private function updateV1User(array $userData): array
    {
        $accessToken = $this->auth->getAccessToken();
        return $this->post('/user/update', $userData, [
            'access_token' => $accessToken,
        ]);
    }

    /**
     * V2版本：更新用户
     */
    private function updateV2User(array $userData): array
    {
        $userId = $userData['userid'];
        unset($userData['userid']);
        
        return $this->put("/v1.0/contact/users/{$userId}", $userData);
    }

    /**
     * V1版本：删除用户
     */
    private function deleteV1User(string $userId): array
    {
        $accessToken = $this->auth->getAccessToken();
        return $this->get('/user/delete', [
            'access_token' => $accessToken,
            'userid' => $userId,
        ]);
    }

    /**
     * V2版本：删除用户
     */
    private function deleteV2User(string $userId): array
    {
        return $this->delete("/v1.0/contact/users/{$userId}");
    }
}