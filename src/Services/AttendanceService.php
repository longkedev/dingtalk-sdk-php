<?php

declare(strict_types=1);

namespace DingTalk\Services;

/**
 * 考勤管理服务
 * 
 * 提供考勤相关的API操作
 */
class AttendanceService extends BaseService
{
    /**
     * 获取用户考勤记录
     */
    public function getUserRecords(array $userIds, string $workDateFrom, string $workDateTo): array
    {
        $this->validateRequired([
            'userIds' => $userIds,
            'workDateFrom' => $workDateFrom,
            'workDateTo' => $workDateTo,
        ], ['userIds', 'workDateFrom', 'workDateTo']);
        
        $apiVersion = $this->config->get('api_version', 'v1');
        
        if ($apiVersion === 'v2') {
            return $this->getV2UserRecords($userIds, $workDateFrom, $workDateTo);
        }
        
        return $this->getV1UserRecords($userIds, $workDateFrom, $workDateTo);
    }

    /**
     * 获取考勤组信息
     */
    public function getGroups(int $offset = 0, int $size = 100): array
    {
        $cacheKey = $this->buildCacheKey('attendance_groups', ['offset' => $offset, 'size' => $size]);
        
        return $this->remember($cacheKey, function () use ($offset, $size) {
            $apiVersion = $this->config->get('api_version', 'v1');
            
            if ($apiVersion === 'v2') {
                return $this->getV2Groups($offset, $size);
            }
            
            return $this->getV1Groups($offset, $size);
        }, 3600); // 缓存1小时
    }

    /**
     * 获取考勤组详情
     */
    public function getGroup(string $groupId): array
    {
        $this->validateRequired(['groupId' => $groupId], ['groupId']);
        
        $cacheKey = $this->buildCacheKey('attendance_group', ['groupId' => $groupId]);
        
        return $this->remember($cacheKey, function () use ($groupId) {
            $apiVersion = $this->config->get('api_version', 'v1');
            
            if ($apiVersion === 'v2') {
                return $this->getV2Group($groupId);
            }
            
            return $this->getV1Group($groupId);
        }, 3600);
    }

    /**
     * 获取用户考勤组
     */
    public function getUserGroups(string $userId): array
    {
        $this->validateRequired(['userId' => $userId], ['userId']);
        
        $cacheKey = $this->buildCacheKey('user_attendance_groups', ['userId' => $userId]);
        
        return $this->remember($cacheKey, function () use ($userId) {
            $apiVersion = $this->config->get('api_version', 'v1');
            
            if ($apiVersion === 'v2') {
                return $this->getV2UserGroups($userId);
            }
            
            return $this->getV1UserGroups($userId);
        }, 1800); // 缓存30分钟
    }

    /**
     * 获取考勤统计数据
     */
    public function getStatistics(array $userIds, string $workDateFrom, string $workDateTo): array
    {
        $this->validateRequired([
            'userIds' => $userIds,
            'workDateFrom' => $workDateFrom,
            'workDateTo' => $workDateTo,
        ], ['userIds', 'workDateFrom', 'workDateTo']);
        
        $apiVersion = $this->config->get('api_version', 'v1');
        
        if ($apiVersion === 'v2') {
            return $this->getV2Statistics($userIds, $workDateFrom, $workDateTo);
        }
        
        return $this->getV1Statistics($userIds, $workDateFrom, $workDateTo);
    }

    /**
     * 获取请假记录
     */
    public function getLeaveRecords(array $userIds, string $startTime, string $endTime): array
    {
        $this->validateRequired([
            'userIds' => $userIds,
            'startTime' => $startTime,
            'endTime' => $endTime,
        ], ['userIds', 'startTime', 'endTime']);
        
        $apiVersion = $this->config->get('api_version', 'v1');
        
        if ($apiVersion === 'v2') {
            return $this->getV2LeaveRecords($userIds, $startTime, $endTime);
        }
        
        return $this->getV1LeaveRecords($userIds, $startTime, $endTime);
    }

    /**
     * 获取加班记录
     */
    public function getOvertimeRecords(array $userIds, string $startTime, string $endTime): array
    {
        $this->validateRequired([
            'userIds' => $userIds,
            'startTime' => $startTime,
            'endTime' => $endTime,
        ], ['userIds', 'startTime', 'endTime']);
        
        $apiVersion = $this->config->get('api_version', 'v1');
        
        if ($apiVersion === 'v2') {
            return $this->getV2OvertimeRecords($userIds, $startTime, $endTime);
        }
        
        return $this->getV1OvertimeRecords($userIds, $startTime, $endTime);
    }

    /**
     * 获取外勤记录
     */
    public function getFieldWorkRecords(array $userIds, string $startTime, string $endTime): array
    {
        $this->validateRequired([
            'userIds' => $userIds,
            'startTime' => $startTime,
            'endTime' => $endTime,
        ], ['userIds', 'startTime', 'endTime']);
        
        $apiVersion = $this->config->get('api_version', 'v1');
        
        if ($apiVersion === 'v2') {
            return $this->getV2FieldWorkRecords($userIds, $startTime, $endTime);
        }
        
        return $this->getV1FieldWorkRecords($userIds, $startTime, $endTime);
    }

    /**
     * 创建考勤打卡记录
     */
    public function createRecord(array $recordData): array
    {
        $required = ['userid', 'check_time', 'location_result'];
        $this->validateRequired($recordData, $required);
        
        $apiVersion = $this->config->get('api_version', 'v1');
        
        if ($apiVersion === 'v2') {
            return $this->createV2Record($recordData);
        }
        
        return $this->createV1Record($recordData);
    }

    /**
     * 批量获取考勤记录
     */
    public function batchGetRecords(array $requests): array
    {
        return $this->batch($requests, function ($batch) {
            $results = [];
            foreach ($batch as $request) {
                try {
                    $results[] = $this->getUserRecords(
                        $request['userIds'],
                        $request['workDateFrom'],
                        $request['workDateTo']
                    );
                } catch (\Exception $e) {
                    $this->logger->warning('Failed to get attendance records', [
                        'request' => $request,
                        'error' => $e->getMessage(),
                    ]);
                    $results[] = ['error' => $e->getMessage()];
                }
            }
            return $results;
        }, 5);
    }

    /**
     * 获取考勤月报
     */
    public function getMonthlyReport(array $userIds, string $month): array
    {
        $this->validateRequired([
            'userIds' => $userIds,
            'month' => $month,
        ], ['userIds', 'month']);
        
        $startDate = $month . '-01';
        $endDate = date('Y-m-t', strtotime($startDate));
        
        return $this->getUserRecords($userIds, $startDate, $endDate);
    }

    /**
     * 获取考勤日报
     */
    public function getDailyReport(array $userIds, string $date): array
    {
        $this->validateRequired([
            'userIds' => $userIds,
            'date' => $date,
        ], ['userIds', 'date']);
        
        return $this->getUserRecords($userIds, $date, $date);
    }

    /**
     * 获取异常考勤记录
     */
    public function getAbnormalRecords(array $userIds, string $workDateFrom, string $workDateTo): array
    {
        $records = $this->getUserRecords($userIds, $workDateFrom, $workDateTo);
        
        // 过滤异常记录
        $abnormalRecords = [];
        foreach ($records['recordresult'] ?? [] as $record) {
            if (isset($record['check_type']) && in_array($record['check_type'], ['Late', 'Early', 'NotSigned'])) {
                $abnormalRecords[] = $record;
            }
        }
        
        return ['recordresult' => $abnormalRecords];
    }

    /**
     * 导出考勤数据
     */
    public function exportData(array $userIds, string $workDateFrom, string $workDateTo, string $format = 'excel'): array
    {
        $this->validateRequired([
            'userIds' => $userIds,
            'workDateFrom' => $workDateFrom,
            'workDateTo' => $workDateTo,
        ], ['userIds', 'workDateFrom', 'workDateTo']);
        
        $apiVersion = $this->config->get('api_version', 'v1');
        
        if ($apiVersion === 'v2') {
            return $this->exportV2Data($userIds, $workDateFrom, $workDateTo, $format);
        }
        
        return $this->exportV1Data($userIds, $workDateFrom, $workDateTo, $format);
    }

    /**
     * V1版本：获取用户考勤记录
     */
    private function getV1UserRecords(array $userIds, string $workDateFrom, string $workDateTo): array
    {
        $accessToken = $this->auth->getAccessToken();
        return $this->post('/topapi/attendance/listRecord', [
            'userIds' => $userIds,
            'checkDateFrom' => $workDateFrom,
            'checkDateTo' => $workDateTo,
        ], [
            'access_token' => $accessToken,
        ]);
    }

    /**
     * V2版本：获取用户考勤记录
     */
    private function getV2UserRecords(array $userIds, string $workDateFrom, string $workDateTo): array
    {
        return $this->post('/v1.0/attendance/getUserRecord', [
            'userIds' => $userIds,
            'fromDate' => $workDateFrom,
            'toDate' => $workDateTo,
        ]);
    }

    /**
     * V1版本：获取考勤组信息
     */
    private function getV1Groups(int $offset, int $size): array
    {
        $accessToken = $this->auth->getAccessToken();
        return $this->post('/topapi/attendance/group/list', [
            'offset' => $offset,
            'size' => $size,
        ], [
            'access_token' => $accessToken,
        ]);
    }

    /**
     * V2版本：获取考勤组信息
     */
    private function getV2Groups(int $offset, int $size): array
    {
        return $this->post('/v1.0/attendance/groups/query', [
            'maxResults' => $size,
            'nextToken' => (string)$offset,
        ]);
    }

    /**
     * V1版本：获取考勤组详情
     */
    private function getV1Group(string $groupId): array
    {
        $accessToken = $this->auth->getAccessToken();
        return $this->post('/topapi/attendance/group/get', [
            'group_id' => $groupId,
        ], [
            'access_token' => $accessToken,
        ]);
    }

    /**
     * V2版本：获取考勤组详情
     */
    private function getV2Group(string $groupId): array
    {
        return $this->get("/v1.0/attendance/groups/{$groupId}");
    }

    /**
     * V1版本：获取用户考勤组
     */
    private function getV1UserGroups(string $userId): array
    {
        $accessToken = $this->auth->getAccessToken();
        return $this->post('/topapi/attendance/group/users/get', [
            'userid' => $userId,
        ], [
            'access_token' => $accessToken,
        ]);
    }

    /**
     * V2版本：获取用户考勤组
     */
    private function getV2UserGroups(string $userId): array
    {
        return $this->get("/v1.0/attendance/users/{$userId}/groups");
    }

    /**
     * V1版本：获取考勤统计数据
     */
    private function getV1Statistics(array $userIds, string $workDateFrom, string $workDateTo): array
    {
        $accessToken = $this->auth->getAccessToken();
        return $this->post('/topapi/attendance/getattcolumns', [
            'userIds' => $userIds,
            'workDateFrom' => $workDateFrom,
            'workDateTo' => $workDateTo,
        ], [
            'access_token' => $accessToken,
        ]);
    }

    /**
     * V2版本：获取考勤统计数据
     */
    private function getV2Statistics(array $userIds, string $workDateFrom, string $workDateTo): array
    {
        return $this->post('/v1.0/attendance/getUserStatistics', [
            'userIds' => $userIds,
            'fromDate' => $workDateFrom,
            'toDate' => $workDateTo,
        ]);
    }

    /**
     * V1版本：获取请假记录
     */
    private function getV1LeaveRecords(array $userIds, string $startTime, string $endTime): array
    {
        $accessToken = $this->auth->getAccessToken();
        return $this->post('/topapi/attendance/getleaveapprovals', [
            'userid_list' => implode(',', $userIds),
            'start_time' => $startTime,
            'end_time' => $endTime,
        ], [
            'access_token' => $accessToken,
        ]);
    }

    /**
     * V2版本：获取请假记录
     */
    private function getV2LeaveRecords(array $userIds, string $startTime, string $endTime): array
    {
        return $this->post('/v1.0/attendance/getLeaveRecords', [
            'userIds' => $userIds,
            'startTime' => $startTime,
            'endTime' => $endTime,
        ]);
    }

    /**
     * V1版本：获取加班记录
     */
    private function getV1OvertimeRecords(array $userIds, string $startTime, string $endTime): array
    {
        $accessToken = $this->auth->getAccessToken();
        return $this->post('/topapi/attendance/getovertimeapprovals', [
            'userid_list' => implode(',', $userIds),
            'start_time' => $startTime,
            'end_time' => $endTime,
        ], [
            'access_token' => $accessToken,
        ]);
    }

    /**
     * V2版本：获取加班记录
     */
    private function getV2OvertimeRecords(array $userIds, string $startTime, string $endTime): array
    {
        return $this->post('/v1.0/attendance/getOvertimeRecords', [
            'userIds' => $userIds,
            'startTime' => $startTime,
            'endTime' => $endTime,
        ]);
    }

    /**
     * V1版本：获取外勤记录
     */
    private function getV1FieldWorkRecords(array $userIds, string $startTime, string $endTime): array
    {
        $accessToken = $this->auth->getAccessToken();
        return $this->post('/topapi/attendance/getoutapprovals', [
            'userid_list' => implode(',', $userIds),
            'start_time' => $startTime,
            'end_time' => $endTime,
        ], [
            'access_token' => $accessToken,
        ]);
    }

    /**
     * V2版本：获取外勤记录
     */
    private function getV2FieldWorkRecords(array $userIds, string $startTime, string $endTime): array
    {
        return $this->post('/v1.0/attendance/getFieldWorkRecords', [
            'userIds' => $userIds,
            'startTime' => $startTime,
            'endTime' => $endTime,
        ]);
    }

    /**
     * V1版本：创建考勤打卡记录
     */
    private function createV1Record(array $recordData): array
    {
        $accessToken = $this->auth->getAccessToken();
        return $this->post('/topapi/attendance/record', $recordData, [
            'access_token' => $accessToken,
        ]);
    }

    /**
     * V2版本：创建考勤打卡记录
     */
    private function createV2Record(array $recordData): array
    {
        return $this->post('/v1.0/attendance/records', $recordData);
    }

    /**
     * V1版本：导出考勤数据
     */
    private function exportV1Data(array $userIds, string $workDateFrom, string $workDateTo, string $format): array
    {
        $accessToken = $this->auth->getAccessToken();
        return $this->post('/topapi/attendance/export', [
            'userIds' => $userIds,
            'workDateFrom' => $workDateFrom,
            'workDateTo' => $workDateTo,
            'format' => $format,
        ], [
            'access_token' => $accessToken,
        ]);
    }

    /**
     * V2版本：导出考勤数据
     */
    private function exportV2Data(array $userIds, string $workDateFrom, string $workDateTo, string $format): array
    {
        return $this->post('/v1.0/attendance/export', [
            'userIds' => $userIds,
            'fromDate' => $workDateFrom,
            'toDate' => $workDateTo,
            'format' => $format,
        ]);
    }
}