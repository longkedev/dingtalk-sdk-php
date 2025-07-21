<?php

declare(strict_types=1);

namespace DingTalk\Exceptions;

/**
 * 错误消息国际化类
 * 
 * 提供多语言错误消息支持，包括错误描述和恢复建议的国际化
 */
class ErrorMessageTranslator
{
    /**
     * 支持的语言列表
     */
    public const SUPPORTED_LANGUAGES = ['en', 'zh', 'ja', 'ko'];

    /**
     * 默认语言
     */
    public const DEFAULT_LANGUAGE = 'en';

    /**
     * 当前语言
     */
    private string $currentLanguage;

    /**
     * 错误消息翻译映射
     */
    private const MESSAGE_TRANSLATIONS = [
        // 认证相关错误消息
        'auth.invalid_access_token' => [
            'en' => 'Invalid access token',
            'zh' => '无效的访问令牌',
            'ja' => '無効なアクセストークン',
            'ko' => '유효하지 않은 액세스 토큰'
        ],
        'auth.access_token_expired' => [
            'en' => 'Access token expired',
            'zh' => '访问令牌已过期',
            'ja' => 'アクセストークンが期限切れです',
            'ko' => '액세스 토큰이 만료되었습니다'
        ],
        'auth.invalid_app_key' => [
            'en' => 'Invalid app key',
            'zh' => '无效的应用密钥',
            'ja' => '無効なアプリキー',
            'ko' => '유효하지 않은 앱 키'
        ],
        'auth.invalid_app_secret' => [
            'en' => 'Invalid app secret',
            'zh' => '无效的应用密钥',
            'ja' => '無効なアプリシークレット',
            'ko' => '유효하지 않은 앱 시크릿'
        ],
        'auth.invalid_credential' => [
            'en' => 'Invalid credential',
            'zh' => '无效的凭证',
            'ja' => '無効な認証情報',
            'ko' => '유효하지 않은 자격 증명'
        ],
        'auth.invalid_corpid' => [
            'en' => 'Invalid corp ID',
            'zh' => '无效的企业ID',
            'ja' => '無効な企業ID',
            'ko' => '유효하지 않은 기업 ID'
        ],

        // API调用相关错误消息
        'api.invalid_parameter' => [
            'en' => 'Invalid parameter',
            'zh' => '无效的参数',
            'ja' => '無効なパラメータ',
            'ko' => '유효하지 않은 매개변수'
        ],
        'api.method_not_allowed' => [
            'en' => 'Method not allowed',
            'zh' => '不允许的方法',
            'ja' => '許可されていないメソッド',
            'ko' => '허용되지 않은 메서드'
        ],
        'api.insufficient_permissions' => [
            'en' => 'Insufficient permissions',
            'zh' => '权限不足',
            'ja' => '権限が不足しています',
            'ko' => '권한이 부족합니다'
        ],
        'api.user_not_exist' => [
            'en' => 'User not exist',
            'zh' => '用户不存在',
            'ja' => 'ユーザーが存在しません',
            'ko' => '사용자가 존재하지 않습니다'
        ],
        'api.department_not_exist' => [
            'en' => 'Department not exist',
            'zh' => '部门不存在',
            'ja' => '部門が存在しません',
            'ko' => '부서가 존재하지 않습니다'
        ],

        // 限流相关错误消息
        'rate_limit.api_rate_limit_exceeded' => [
            'en' => 'API rate limit exceeded',
            'zh' => 'API调用频率超限',
            'ja' => 'APIレート制限を超過しました',
            'ko' => 'API 속도 제한을 초과했습니다'
        ],
        'rate_limit.quota_exceeded' => [
            'en' => 'Quota exceeded',
            'zh' => '配额已超出',
            'ja' => 'クォータを超過しました',
            'ko' => '할당량을 초과했습니다'
        ],
        'rate_limit.interface_call_frequency_limit' => [
            'en' => 'Interface call frequency limit',
            'zh' => '接口调用频率限制',
            'ja' => 'インターフェース呼び出し頻度制限',
            'ko' => '인터페이스 호출 빈도 제한'
        ],

        // 网络相关错误消息
        'network.internal_server_error' => [
            'en' => 'Internal server error',
            'zh' => '内部服务器错误',
            'ja' => '内部サーバーエラー',
            'ko' => '내부 서버 오류'
        ],
        'network.service_unavailable' => [
            'en' => 'Service unavailable',
            'zh' => '服务不可用',
            'ja' => 'サービス利用不可',
            'ko' => '서비스를 사용할 수 없습니다'
        ],
        'network.connection_timeout' => [
            'en' => 'Connection timeout',
            'zh' => '连接超时',
            'ja' => '接続タイムアウト',
            'ko' => '연결 시간 초과'
        ],

        // 配置相关错误消息
        'config.file_not_found' => [
            'en' => 'Configuration file not found',
            'zh' => '配置文件未找到',
            'ja' => '設定ファイルが見つかりません',
            'ko' => '구성 파일을 찾을 수 없습니다'
        ],
        'config.invalid_format' => [
            'en' => 'Invalid configuration format',
            'zh' => '无效的配置格式',
            'ja' => '無効な設定フォーマット',
            'ko' => '유효하지 않은 구성 형식'
        ],

        // 验证相关错误消息
        'validation.parameter_validation_failed' => [
            'en' => 'Parameter validation failed',
            'zh' => '参数验证失败',
            'ja' => 'パラメータ検証に失敗しました',
            'ko' => '매개변수 검증에 실패했습니다'
        ],

        // 容器相关错误消息
        'container.service_not_found' => [
            'en' => 'Service not found in container',
            'zh' => '容器中未找到服务',
            'ja' => 'コンテナ内でサービスが見つかりません',
            'ko' => '컨테이너에서 서비스를 찾을 수 없습니다'
        ],

        // 通用错误消息
        'unknown.error' => [
            'en' => 'Unknown error',
            'zh' => '未知错误',
            'ja' => '不明なエラー',
            'ko' => '알 수 없는 오류'
        ]
    ];

    /**
     * 恢复建议翻译映射
     */
    private const RECOVERY_TRANSLATIONS = [
        // 认证相关恢复建议
        'auth.refresh_access_token' => [
            'en' => 'Please refresh your access token',
            'zh' => '请刷新您的访问令牌',
            'ja' => 'アクセストークンを更新してください',
            'ko' => '액세스 토큰을 새로 고치십시오'
        ],
        'auth.obtain_new_access_token' => [
            'en' => 'Please obtain a new access token',
            'zh' => '请获取新的访问令牌',
            'ja' => '新しいアクセストークンを取得してください',
            'ko' => '새 액세스 토큰을 얻으십시오'
        ],
        'auth.check_app_key_config' => [
            'en' => 'Please check your app key configuration',
            'zh' => '请检查您的应用密钥配置',
            'ja' => 'アプリキーの設定を確認してください',
            'ko' => '앱 키 구성을 확인하십시오'
        ],
        'auth.check_app_secret_config' => [
            'en' => 'Please check your app secret configuration',
            'zh' => '请检查您的应用密钥配置',
            'ja' => 'アプリシークレットの設定を確認してください',
            'ko' => '앱 시크릿 구성을 확인하십시오'
        ],
        'auth.check_credentials' => [
            'en' => 'Please check your credentials',
            'zh' => '请检查您的凭证',
            'ja' => '認証情報を確認してください',
            'ko' => '자격 증명을 확인하십시오'
        ],
        'auth.check_corp_id' => [
            'en' => 'Please check your corp ID',
            'zh' => '请检查您的企业ID',
            'ja' => '企業IDを確認してください',
            'ko' => '기업 ID를 확인하십시오'
        ],

        // API调用相关恢复建议
        'api.check_parameter_format' => [
            'en' => 'Please check the parameter format and values',
            'zh' => '请检查参数格式和值',
            'ja' => 'パラメータの形式と値を確認してください',
            'ko' => '매개변수 형식과 값을 확인하십시오'
        ],
        'api.use_correct_http_method' => [
            'en' => 'Please use the correct HTTP method',
            'zh' => '请使用正确的HTTP方法',
            'ja' => '正しいHTTPメソッドを使用してください',
            'ko' => '올바른 HTTP 메서드를 사용하십시오'
        ],
        'api.check_application_permissions' => [
            'en' => 'Please check your application permissions',
            'zh' => '请检查您的应用权限',
            'ja' => 'アプリケーションの権限を確認してください',
            'ko' => '애플리케이션 권한을 확인하십시오'
        ],
        'api.check_user_id' => [
            'en' => 'Please check the user ID',
            'zh' => '请检查用户ID',
            'ja' => 'ユーザーIDを確認してください',
            'ko' => '사용자 ID를 확인하십시오'
        ],
        'api.check_department_id' => [
            'en' => 'Please check the department ID',
            'zh' => '请检查部门ID',
            'ja' => '部門IDを確认してください',
            'ko' => '부서 ID를 확인하십시오'
        ],

        // 限流相关恢复建议
        'rate_limit.reduce_request_frequency' => [
            'en' => 'Please reduce the request frequency',
            'zh' => '请降低请求频率',
            'ja' => 'リクエスト頻度を下げてください',
            'ko' => '요청 빈도를 줄이십시오'
        ],
        'rate_limit.wait_quota_reset_or_upgrade' => [
            'en' => 'Please wait for quota reset or upgrade your plan',
            'zh' => '请等待配额重置或升级您的套餐',
            'ja' => 'クォータのリセットを待つか、プランをアップグレードしてください',
            'ko' => '할당량 재설정을 기다리거나 플랜을 업그레이드하십시오'
        ],

        // 网络相关恢复建议
        'network.try_again_later' => [
            'en' => 'Please try again later',
            'zh' => '请稍后重试',
            'ja' => '後でもう一度お試しください',
            'ko' => '나중에 다시 시도하십시오'
        ],
        'network.check_service_status' => [
            'en' => 'Please check service status and try again',
            'zh' => '请检查服务状态并重试',
            'ja' => 'サービスステータスを確認して再試行してください',
            'ko' => '서비스 상태를 확인하고 다시 시도하십시오'
        ],
        'network.check_network_connection' => [
            'en' => 'Please check your network connection',
            'zh' => '请检查您的网络连接',
            'ja' => 'ネットワーク接続を確認してください',
            'ko' => '네트워크 연결을 확인하십시오'
        ],

        // 配置相关恢复建议
        'config.create_configuration_file' => [
            'en' => 'Please create the configuration file',
            'zh' => '请创建配置文件',
            'ja' => '設定ファイルを作成してください',
            'ko' => '구성 파일을 생성하십시오'
        ],
        'config.check_configuration_format' => [
            'en' => 'Please check the configuration file format',
            'zh' => '请检查配置文件格式',
            'ja' => '設定ファイルの形式を確認してください',
            'ko' => '구성 파일 형식을 확인하십시오'
        ],

        // 验证相关恢复建议
        'validation.check_parameter_format' => [
            'en' => 'Please check the parameter format',
            'zh' => '请检查参数格式',
            'ja' => 'パラメータの形式を確認してください',
            'ko' => '매개변수 형식을 확인하십시오'
        ],

        // 容器相关恢复建议
        'container.register_service' => [
            'en' => 'Please register the service in container',
            'zh' => '请在容器中注册服务',
            'ja' => 'コンテナにサービスを登録してください',
            'ko' => '컨테이너에 서비스를 등록하십시오'
        ],

        // 通用恢复建议
        'unknown.contact_support' => [
            'en' => 'Please contact technical support',
            'zh' => '请联系技术支持',
            'ja' => 'テクニカルサポートにお問い合わせください',
            'ko' => '기술 지원에 문의하십시오'
        ]
    ];

    /**
     * 构造函数
     */
    public function __construct(string $language = self::DEFAULT_LANGUAGE)
    {
        $this->setLanguage($language);
    }

    /**
     * 设置当前语言
     */
    public function setLanguage(string $language): self
    {
        if (!in_array($language, self::SUPPORTED_LANGUAGES, true)) {
            $language = self::DEFAULT_LANGUAGE;
        }
        
        $this->currentLanguage = $language;
        return $this;
    }

    /**
     * 获取当前语言
     */
    public function getLanguage(): string
    {
        return $this->currentLanguage;
    }

    /**
     * 获取支持的语言列表
     */
    public function getSupportedLanguages(): array
    {
        return self::SUPPORTED_LANGUAGES;
    }

    /**
     * 翻译错误消息
     */
    public function translateMessage(string $messageKey, ?string $language = null): string
    {
        $language = $language ?? $this->currentLanguage;
        
        if (!isset(self::MESSAGE_TRANSLATIONS[$messageKey])) {
            return $this->translateMessage('unknown.error', $language);
        }

        $translations = self::MESSAGE_TRANSLATIONS[$messageKey];
        
        return $translations[$language] ?? $translations[self::DEFAULT_LANGUAGE] ?? $messageKey;
    }

    /**
     * 翻译恢复建议
     */
    public function translateRecovery(string $recoveryKey, ?string $language = null): string
    {
        $language = $language ?? $this->currentLanguage;
        
        if (!isset(self::RECOVERY_TRANSLATIONS[$recoveryKey])) {
            return $this->translateRecovery('unknown.contact_support', $language);
        }

        $translations = self::RECOVERY_TRANSLATIONS[$recoveryKey];
        
        return $translations[$language] ?? $translations[self::DEFAULT_LANGUAGE] ?? $recoveryKey;
    }

    /**
     * 批量翻译消息
     */
    public function translateMessages(array $messageKeys, ?string $language = null): array
    {
        $result = [];
        foreach ($messageKeys as $key) {
            $result[$key] = $this->translateMessage($key, $language);
        }
        return $result;
    }

    /**
     * 批量翻译恢复建议
     */
    public function translateRecoveries(array $recoveryKeys, ?string $language = null): array
    {
        $result = [];
        foreach ($recoveryKeys as $key) {
            $result[$key] = $this->translateRecovery($key, $language);
        }
        return $result;
    }

    /**
     * 检查消息键是否存在
     */
    public function hasMessage(string $messageKey): bool
    {
        return isset(self::MESSAGE_TRANSLATIONS[$messageKey]);
    }

    /**
     * 检查恢复建议键是否存在
     */
    public function hasRecovery(string $recoveryKey): bool
    {
        return isset(self::RECOVERY_TRANSLATIONS[$recoveryKey]);
    }

    /**
     * 获取所有消息键
     */
    public function getAllMessageKeys(): array
    {
        return array_keys(self::MESSAGE_TRANSLATIONS);
    }

    /**
     * 获取所有恢复建议键
     */
    public function getAllRecoveryKeys(): array
    {
        return array_keys(self::RECOVERY_TRANSLATIONS);
    }

    /**
     * 添加自定义翻译
     */
    public function addCustomTranslation(string $key, array $translations, string $type = 'message'): self
    {
        // 注意：这里只是示例，实际实现中可能需要持久化存储
        // 由于使用了const定义，这里只能在运行时临时添加
        return $this;
    }
}