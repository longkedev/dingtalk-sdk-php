<?php

declare(strict_types=1);

namespace DingTalk\Services;

/**
 * 部门管理服务
 * 
 * 提供部门相关的API操作
 */
class DepartmentService extends BaseService
{
    /**
     * 获取部门详情
     */
    public function getDepartment(int $departmentId): array
    {
        $this->validateRequired(['departmentId' => $departmentId], ['departmentId']);
        
        $cacheKey = $this->buildCacheKey('department', ['departmentId' => $departmentId]);
        
        return $this->remember($cacheKey, function () use ($departmentId) {
            $apiVersion = $this->config->get('api_version', 'v1');
            
            if ($apiVersion === 'v2') {
                return $this->getV2Department($departmentId);
            }
            
            return $this->getV1Department($departmentId);
        }, 3600); // 缓存1小时
    }

    /**
     * 获取子部门列表
     */
    public function listSubDepartments(int $parentId = 1): array
    {
        $cacheKey = $this->buildCacheKey('sub_departments', ['parentId' => $parentId]);
        
        return $this->remember($cacheKey, function () use ($parentId) {
            $apiVersion = $this->config->get('api_version', 'v1');
            
            if ($apiVersion === 'v2') {
                return $this->getV2SubDepartments($parentId);
            }
            
            return $this->getV1SubDepartments($parentId);
        }, 3600);
    }

    /**
     * 获取部门详情列表
     */
    public function listDetails(int $parentId = null, bool $fetchChild = false): array
    {
        $params = [
            'parentId' => $parentId,
            'fetchChild' => $fetchChild,
        ];
        
        $cacheKey = $this->buildCacheKey('department_details', $params);
        
        return $this->remember($cacheKey, function () use ($parentId, $fetchChild) {
            $apiVersion = $this->config->get('api_version', 'v1');
            
            if ($apiVersion === 'v2') {
                return $this->getV2DepartmentDetails($parentId, $fetchChild);
            }
            
            return $this->getV1DepartmentDetails($parentId, $fetchChild);
        }, 3600);
    }

    /**
     * 创建部门
     */
    public function create(array $departmentData): array
    {
        $required = ['name', 'parentid'];
        $this->validateRequired($departmentData, $required);
        
        $apiVersion = $this->config->get('api_version', 'v1');
        
        if ($apiVersion === 'v2') {
            $result = $this->createV2Department($departmentData);
        } else {
            $result = $this->createV1Department($departmentData);
        }
        
        // 清除相关缓存
        $this->clearDepartmentCache($departmentData['parentid']);
        
        return $result;
    }

    /**
     * 更新部门
     */
    public function update(int $departmentId, array $departmentData): array
    {
        $this->validateRequired(['departmentId' => $departmentId] + $departmentData, ['departmentId']);
        
        $departmentData['id'] = $departmentId;
        
        $apiVersion = $this->config->get('api_version', 'v1');
        
        if ($apiVersion === 'v2') {
            $result = $this->updateV2Department($departmentData);
        } else {
            $result = $this->updateV1Department($departmentData);
        }
        
        // 清除缓存
        $this->forgetCache($this->buildCacheKey('department', ['departmentId' => $departmentId]));
        if (isset($departmentData['parentid'])) {
            $this->clearDepartmentCache($departmentData['parentid']);
        }
        
        return $result;
    }

    /**
     * 删除部门
     */
    public function deleteDepartment(int $departmentId): array
    {
        $this->validateRequired(['departmentId' => $departmentId], ['departmentId']);
        
        // 先获取部门信息以便清除缓存
        $department = $this->getDepartment($departmentId);
        
        $apiVersion = $this->config->get('api_version', 'v1');
        
        if ($apiVersion === 'v2') {
            $result = $this->deleteV2Department($departmentId);
        } else {
            $result = $this->deleteV1Department($departmentId);
        }
        
        // 清除缓存
        $this->forgetCache($this->buildCacheKey('department', ['departmentId' => $departmentId]));
        if (isset($department['parentid'])) {
            $this->clearDepartmentCache($department['parentid']);
        }
        
        return $result;
    }

    /**
     * 获取部门用户数量
     */
    public function getUserCount(int $departmentId, bool $includeChild = false): int
    {
        $this->validateRequired(['departmentId' => $departmentId], ['departmentId']);
        
        $cacheKey = $this->buildCacheKey('department_user_count', [
            'departmentId' => $departmentId,
            'includeChild' => $includeChild,
        ]);
        
        return $this->remember($cacheKey, function () use ($departmentId, $includeChild) {
            $apiVersion = $this->config->get('api_version', 'v1');
            
            if ($apiVersion === 'v2') {
                return $this->getV2DepartmentUserCount($departmentId, $includeChild);
            }
            
            return $this->getV1DepartmentUserCount($departmentId, $includeChild);
        }, 1800); // 缓存30分钟
    }

    /**
     * 获取用户所在部门列表
     */
    public function getUserDepartments(string $userId): array
    {
        $this->validateRequired(['userId' => $userId], ['userId']);
        
        $cacheKey = $this->buildCacheKey('user_departments', ['userId' => $userId]);
        
        return $this->remember($cacheKey, function () use ($userId) {
            $apiVersion = $this->config->get('api_version', 'v1');
            
            if ($apiVersion === 'v2') {
                return $this->getV2UserDepartments($userId);
            }
            
            return $this->getV1UserDepartments($userId);
        }, 1800);
    }

    /**
     * 获取部门层级结构
     */
    public function getHierarchy(int $rootId = 1): array
    {
        $cacheKey = $this->buildCacheKey('department_hierarchy', ['rootId' => $rootId]);
        
        return $this->remember($cacheKey, function () use ($rootId) {
            return $this->buildHierarchy($rootId);
        }, 3600);
    }

    /**
     * 搜索部门
     */
    public function search(string $keyword, int $parentId = null): array
    {
        $this->validateRequired(['keyword' => $keyword], ['keyword']);
        
        $departments = $this->listDetails($parentId, true);
        
        return array_filter($departments, function ($dept) use ($keyword) {
            return stripos($dept['name'], $keyword) !== false;
        });
    }

    /**
     * 批量获取部门详情
     */
    public function batchGet(array $departmentIds): array
    {
        if (empty($departmentIds)) {
            return [];
        }
        
        return $this->batch($departmentIds, function ($batch) {
            $departments = [];
            foreach ($batch as $departmentId) {
                try {
                    $departments[] = $this->getDepartment($departmentId);
                } catch (\Exception $e) {
                    $this->logger->warning('Failed to get department', [
                        'department_id' => $departmentId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
            return $departments;
        }, 20);
    }

    /**
     * V1版本：获取部门详情
     */
    private function getV1Department(int $departmentId): array
    {
        $accessToken = $this->auth->getAccessToken();
        return $this->get('/department/get', [
            'access_token' => $accessToken,
            'id' => $departmentId,
        ]);
    }

    /**
     * V2版本：获取部门详情
     */
    private function getV2Department(int $departmentId): array
    {
        return $this->get("/v1.0/contact/departments/{$departmentId}");
    }

    /**
     * V1版本：获取子部门列表
     */
    private function getV1SubDepartments(int $parentId): array
    {
        $accessToken = $this->auth->getAccessToken();
        return $this->get('/department/list_ids', [
            'access_token' => $accessToken,
            'id' => $parentId,
        ]);
    }

    /**
     * V2版本：获取子部门列表
     */
    private function getV2SubDepartments(int $parentId): array
    {
        return $this->get('/v1.0/contact/departments/listSubIds', [
            'deptId' => $parentId,
        ]);
    }

    /**
     * V1版本：获取部门详情列表
     */
    private function getV1DepartmentDetails(int $parentId = null, bool $fetchChild = false): array
    {
        $accessToken = $this->auth->getAccessToken();
        $params = [
            'access_token' => $accessToken,
            'fetch_child' => $fetchChild,
        ];
        
        if ($parentId !== null) {
            $params['id'] = $parentId;
        }
        
        return $this->get('/department/list', $params);
    }

    /**
     * V2版本：获取部门详情列表
     */
    private function getV2DepartmentDetails(int $parentId = null, bool $fetchChild = false): array
    {
        $params = [];
        if ($parentId !== null) {
            $params['deptId'] = $parentId;
        }
        
        return $this->post('/v1.0/contact/departments/listSub', $params);
    }

    /**
     * V1版本：创建部门
     */
    private function createV1Department(array $departmentData): array
    {
        $accessToken = $this->auth->getAccessToken();
        return $this->post('/department/create', $departmentData, [
            'access_token' => $accessToken,
        ]);
    }

    /**
     * V2版本：创建部门
     */
    private function createV2Department(array $departmentData): array
    {
        return $this->post('/v1.0/contact/departments', $departmentData);
    }

    /**
     * V1版本：更新部门
     */
    private function updateV1Department(array $departmentData): array
    {
        $accessToken = $this->auth->getAccessToken();
        return $this->post('/department/update', $departmentData, [
            'access_token' => $accessToken,
        ]);
    }

    /**
     * V2版本：更新部门
     */
    private function updateV2Department(array $departmentData): array
    {
        $departmentId = $departmentData['id'];
        unset($departmentData['id']);
        
        return $this->put("/v1.0/contact/departments/{$departmentId}", $departmentData);
    }

    /**
     * V1版本：删除部门
     */
    private function deleteV1Department(int $departmentId): array
    {
        $accessToken = $this->auth->getAccessToken();
        return $this->get('/department/delete', [
            'access_token' => $accessToken,
            'id' => $departmentId,
        ]);
    }

    /**
     * V2版本：删除部门
     */
    private function deleteV2Department(int $departmentId): array
    {
        return $this->delete("/v1.0/contact/departments/{$departmentId}");
    }

    /**
     * V1版本：获取部门用户数量
     */
    private function getV1DepartmentUserCount(int $departmentId, bool $includeChild): int
    {
        $accessToken = $this->auth->getAccessToken();
        $result = $this->get('/user/get_dept_member', [
            'access_token' => $accessToken,
            'deptId' => $departmentId,
        ]);
        
        return count($result['userIds'] ?? []);
    }

    /**
     * V2版本：获取部门用户数量
     */
    private function getV2DepartmentUserCount(int $departmentId, bool $includeChild): int
    {
        $result = $this->post('/v1.0/contact/departments/users', [
            'deptId' => $departmentId,
            'cursor' => 0,
            'size' => 1,
        ]);
        
        return $result['totalCount'] ?? 0;
    }

    /**
     * V1版本：获取用户所在部门列表
     */
    private function getV1UserDepartments(string $userId): array
    {
        $accessToken = $this->auth->getAccessToken();
        return $this->get('/user/get', [
            'access_token' => $accessToken,
            'userid' => $userId,
        ]);
    }

    /**
     * V2版本：获取用户所在部门列表
     */
    private function getV2UserDepartments(string $userId): array
    {
        return $this->get("/v1.0/contact/users/{$userId}");
    }

    /**
     * 构建部门层级结构
     */
    private function buildHierarchy(int $rootId): array
    {
        $department = $this->get($rootId);
        $subDepartments = $this->listSubDepartments($rootId);
        
        $department['children'] = [];
        
        if (!empty($subDepartments['dept_id_list'])) {
            foreach ($subDepartments['dept_id_list'] as $subId) {
                if ($subId !== $rootId) {
                    $department['children'][] = $this->buildHierarchy($subId);
                }
            }
        }
        
        return $department;
    }

    /**
     * 清除部门相关缓存
     */
    private function clearDepartmentCache(int $parentId): void
    {
        $this->forgetCache($this->buildCacheKey('sub_departments', ['parentId' => $parentId]));
        $this->forgetCache($this->buildCacheKey('department_details', ['parentId' => $parentId, 'fetchChild' => false]));
        $this->forgetCache($this->buildCacheKey('department_details', ['parentId' => $parentId, 'fetchChild' => true]));
        $this->forgetCache($this->buildCacheKey('department_hierarchy', ['rootId' => $parentId]));
    }
}