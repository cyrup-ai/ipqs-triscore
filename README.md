<div align="center">
  <img src="assets/img/banner.png" alt="Kodegen AI Banner" width="100%" />
</div>

# IPQS TriScore - Fraud Detection Library for PHP 8+

[![License](https://img.shields.io/badge/license-MIT%20%7C%20Apache--2.0-blue.svg)](LICENSE.md)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.1-8892BF.svg)](https://php.net)

A production-ready PHP library for fraud detection using [IPQualityScore](https://www.ipqualityscore.com) APIs. Combines email, phone, and IP address reputation scoring into a unified risk assessment with intelligent caching and override rules.

## What This Library Provides

### 🎯 Core Features

**Tri-Risk Fraud Evaluation** - Combines three fraud signals into a single risk category:
- **Email Reputation** - Validates email addresses, detects disposable/spam domains
- **IP Reputation** - Identifies proxies, VPNs, Tor, and high-risk networks
- **Phone Reputation** - Validates phone numbers, detects VOIP/burner phones

**Intelligent Risk Categorization**:
- `LOW` - Safe to proceed (avgScore ≤ 50)
- `MEDIUM` - Requires additional verification (avgScore 51-75)
- `HIGH` - High fraud risk (avgScore ≥ 76)

**IP-Based Override Rules**:
- Elevates `LOW` → `MEDIUM` when IP fraud score ≥ 75
- Elevates `MEDIUM` → `HIGH` when IP fraud score ≥ 88
- Conservative approach: Only elevations, never downgrades

### 🚀 Performance Features

**Smart Caching Strategy**:
- Email scores: 90-day cache (user attributes change slowly)
- Phone scores: 90-day cache (phone numbers rarely change)
- IP scores: **3-day cache** (IPs reassigned frequently, VPNs rotate)
- Reduces API costs by ~90% in production

**Graceful Degradation**:
- Missing channels are silently skipped
- Services continue working even if some APIs fail
- Returns null on errors (never throws exceptions)

### 🏗️ Architecture

```
src/
├── Cache/
│   └── CacheInterface.php         # PSR-16 simple cache interface
├── Client/
│   ├── EmailClient.php            # IPQS Email Validation API
│   ├── IpClient.php               # IPQS Proxy Detection API
│   └── PhoneClient.php            # IPQS Phone Validation API
├── Config/
│   └── IpqsConfig.php             # Configuration management
├── Enum/
│   ├── AbuseVelocity.php          # IP abuse velocity (none/low/medium/high)
│   ├── ConnectionType.php         # IP connection type (residential/corporate/etc)
│   └── RiskCategory.php           # Risk categories (LOW/MEDIUM/HIGH)
├── Model/
│   ├── EmailQualityScore.php     # Email fraud score + metadata
│   ├── FraudEvaluationResult.php # Tri-risk evaluation result
│   ├── IpQualityScore.php        # IP fraud score + geo data
│   └── PhoneQualityScore.php     # Phone fraud score + metadata
├── Service/
│   ├── EmailQualityScoreService.php  # Cached email scoring (90-day TTL)
│   ├── IpQualityScoreService.php     # Cached IP scoring (3-day TTL)
│   └── PhoneQualityScoreService.php  # Cached phone scoring (90-day TTL)
└── TriRisk/
    └── TriRiskEvaluator.php      # Tri-risk fraud evaluation algorithm
```

---

## Quick Start for Developers

### Prerequisites

- PHP 8.1 or higher
- Composer
- IPQualityScore API key ([sign up here](https://www.ipqualityscore.com))

### 1. Clone and Install

```bash
# Clone the repository
git clone <repository-url>
cd ipqs-triscore

# Install dependencies
composer install
```

### 2. Environment Setup

Create a `.env` file (or set environment variables):

```bash
# IPQualityScore API Configuration
IPQS_API_KEY=your_api_key_here
IPQS_BASE_URL=https://ipqualityscore.com/api/json  # Optional, uses default if not set
IPQS_TIMEOUT=10                                     # Optional, default: 10 seconds
IPQS_STRICTNESS=0                                   # Optional, 0-3 (default: 0)
```

### 3. Implement Cache Provider

The library requires a PSR-16 compatible cache implementation. Here's a simple example using file cache:

```php
<?php
use Kodegen\Ipqs\Cache\CacheInterface;

class FileCache implements CacheInterface
{
    private string $cacheDir;

    public function __construct(string $cacheDir = '/tmp/ipqs-cache')
    {
        $this->cacheDir = $cacheDir;
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }
    }

    public function get(string $key): mixed
    {
        $file = $this->cacheDir . '/' . md5($key);
        if (!file_exists($file)) {
            return null;
        }

        $data = unserialize(file_get_contents($file));
        if ($data['expires'] < time()) {
            unlink($file);
            return null;
        }

        return $data['value'];
    }

    public function set(string $key, mixed $value, int $ttl): void
    {
        $file = $this->cacheDir . '/' . md5($key);
        $data = [
            'value' => $value,
            'expires' => time() + $ttl,
        ];
        file_put_contents($file, serialize($data));
    }

    public function delete(string $key): void
    {
        $file = $this->cacheDir . '/' . md5($key);
        if (file_exists($file)) {
            unlink($file);
        }
    }
}
```

**Production Recommendation**: Use Redis, Memcached, or your framework's cache (Laravel Cache, Symfony Cache, etc.)

### 4. Basic Usage Example

```php
<?php
use Kodegen\Ipqs\Config\IpqsConfig;
use Kodegen\Ipqs\Client\{EmailClient, IpClient, PhoneClient};
use Kodegen\Ipqs\Service\{EmailQualityScoreService, IpQualityScoreService, PhoneQualityScoreService};
use Kodegen\Ipqs\TriRisk\TriRiskEvaluator;
use Kodegen\Ipqs\Enum\RiskCategory;
use Psr\Log\NullLogger;

// 1. Setup configuration
$config = IpqsConfig::fromEnv();

// 2. Setup cache and logger
$cache = new FileCache(); // Or your PSR-16 cache implementation
$logger = new NullLogger(); // Or your PSR-3 logger

// 3. Initialize HTTP clients
$emailClient = new EmailClient($config, $logger);
$ipClient = new IpClient($config, $logger);
$phoneClient = new PhoneClient($config, $logger);

// 4. Initialize services (with caching)
$emailService = new EmailQualityScoreService($emailClient, $cache, $logger);
$ipService = new IpQualityScoreService($ipClient, $cache, $logger);
$phoneService = new PhoneQualityScoreService($phoneClient, $cache, $logger);

// 5. Create tri-risk evaluator
$evaluator = new TriRiskEvaluator($emailService, $ipService, $phoneService);

// 6. Evaluate fraud risk
$result = $evaluator->evaluate(
    email: 'user@example.com',
    ipAddress: '192.168.1.1',
    userAgent: 'Mozilla/5.0...',
    phoneNumber: '+15551234567'
);

// 7. Handle result
match ($result->riskCategory) {
    RiskCategory::LOW => handleLowRisk($result),
    RiskCategory::MEDIUM => requireAdditionalVerification($result),
    RiskCategory::HIGH => blockOrFlag($result),
};

// Access detailed scores
echo "Average Score: {$result->avgScore}\n";
echo "Email Score: {$result->emailFraudScore}\n";
echo "IP Score: {$result->ipFraudScore}\n";
echo "Phone Score: {$result->phoneFraudScore}\n";

// Access IP metadata
if (isset($result->metadata['ip'])) {
    $ip = $result->metadata['ip'];
    echo "Country: {$ip['countryCode']}\n";
    echo "Proxy: " . ($ip['proxy'] ? 'Yes' : 'No') . "\n";
    echo "VPN: " . ($ip['vpn'] ? 'Yes' : 'No') . "\n";
}
```

---

## ⚠️ Security Notice: API Key Exposure

**IMPORTANT:** The IPQualityScore API requires API keys to be embedded in URL paths (e.g., `/api/json/ip/{API_KEY}/{IP_ADDRESS}`). This is a design decision by IPQualityScore and cannot be changed.

### Implications

API keys will appear in:
- Web server access logs (nginx, Apache)
- HTTP proxy logs (HAProxy, Varnish)
- Load balancer logs (AWS ALB, CloudFront)
- Network monitoring tools
- Any system that logs HTTP requests

### Mitigation

This library implements **URL sanitization** in all log messages:
- Logger calls sanitize URLs before logging (API keys replaced with `***REDACTED***`)
- See `Kodegen\Ipqs\Util\UrlSanitizer` for implementation
- Only application-level logs are protected; infrastructure logs require separate configuration

### Recommendations

1. **Secure your logs:** Ensure log files have restricted permissions (600 or 640)
2. **Log rotation:** Implement log rotation with secure deletion of old logs
3. **Centralized logging:** If using centralized logging, ensure transport is encrypted (TLS)
4. **API key rotation:** Rotate API keys regularly through IPQualityScore dashboard
5. **Infrastructure logging:** Configure web servers/proxies to not log URLs matching `/api/json/` patterns

### Infrastructure-Level Log Sanitization

**Nginx Example:**

```nginx
# Custom log format that sanitizes IPQS API URLs
log_format sanitized '$remote_addr - $remote_user [$time_local] '
                     '"$request_method $sanitized_uri $server_protocol" '
                     '$status $body_bytes_sent "$http_referer" '
                     '"$http_user_agent"';

# Map to create sanitized URI
map $request_uri $sanitized_uri {
    ~^(/api/json/(?:ip|email|phone)/)([^/]+)(/.+)$ "$1***REDACTED***$3";
    default $request_uri;
}

# Use sanitized log format
access_log /var/log/nginx/access.log sanitized;
```

**Apache Example:**

```apache
# Define custom log format with sanitization
# Note: Apache doesn't support inline regex substitution like nginx
# Consider using mod_rewrite or logging only non-sensitive parts

# Option 1: Don't log IPQS API calls at all
SetEnvIf Request_URI "^/api/json/(ip|email|phone)/" dontlog
CustomLog logs/access_log combined env=!dontlog

# Option 2: Use external log processing (e.g., logrotate with sed)
# Postrotate script:
# sed -i 's#/api/json/\(ip\|email\|phone\)/[^/]\+/#/api/json/\1/***REDACTED***/#g' /var/log/apache2/access.log
```

### Alternative: API Gateway Pattern

For maximum security, consider deploying an API gateway/proxy:

```
[Your App] → [Internal Gateway] → [IPQS API]
           (header auth)      (path auth)
```

The gateway:
- Authenticates your app via headers (Authorization: Bearer token)
- Proxies requests to IPQS with path-based auth
- Keeps API keys centralized and out of application logs
- Enables centralized key rotation

**Example with nginx:**

```nginx
# Internal API endpoint (your app calls this)
location /api/ipqs/ {
    # Require header-based auth from your app
    if ($http_authorization != "Bearer your-internal-token") {
        return 403;
    }

    # Proxy to IPQS with API key in path
    rewrite ^/api/ipqs/ip/(.+)$ /api/json/ip/$IPQS_API_KEY/$1 break;
    proxy_pass https://ipqualityscore.com;

    # Don't log this location (contains API key)
    access_log off;
}
```

### API Key Rotation Procedure

**Recommended rotation schedule:** Every 90 days minimum, or immediately if compromise suspected.

**Rotation steps:**
1. Generate new API key in IPQualityScore dashboard
2. Update `IPQS_API_KEY` environment variable in your deployment
3. Deploy updated configuration
4. Monitor for errors (old key still in use somewhere)
5. After 24h grace period, revoke old key in IPQS dashboard
6. Verify all services working with new key

**Automation:** Consider using secret management tools:
- AWS Secrets Manager (automatic rotation)
- HashiCorp Vault
- Azure Key Vault
- Google Secret Manager

### Compliance Considerations

**OWASP API Security Top 10:** This limitation violates "Broken Authentication" (API keys in URLs)
**PCI DSS:** May require additional controls if processing payment data
**GDPR/Privacy:** Ensure logs containing URLs are properly secured
**SOC 2:** Document this limitation in security policies and compensating controls

### Secret Scanning

**Recommended tools:**
- GitGuardian: Scans repositories for exposed secrets
- TruffleHog: Finds secrets in git history
- git-secrets: Prevents committing secrets

**Pre-commit hook example:**

```bash
#!/bin/bash
# .git/hooks/pre-commit

# Check for IPQS API keys in staged files
if git grep -E 'IPQS_API_KEY\s*=\s*[a-zA-Z0-9]{20,}' $(git diff --cached --name-only); then
    echo "ERROR: IPQS API key found in staged files!"
    echo "API keys should only be in .env (which is .gitignored)"
    exit 1
fi
```

---

## Advanced Usage

### Partial Data (Only IP)

```php
// Evaluate with only IP data available
$result = $evaluator->evaluate(
    ipAddress: '192.168.1.1',
    userAgent: 'Mozilla/5.0...'
);

// Email and Phone scores will be null
// avgScore calculated from IP score alone
```

### Pre-Fetched Scores (Batch Processing)

```php
// Pre-fetch scores from database or cache
$emailScore = $emailService->score('user@example.com');
$ipScore = $ipService->score('192.168.1.1', 'Mozilla/5.0...');
$phoneScore = $phoneService->score('+15551234567');

// Evaluate without calling services (performance optimization)
$result = $evaluator->evaluate(
    emailScore: $emailScore,
    ipScore: $ipScore,
    phoneScore: $phoneScore
);
```

### Individual Service Usage

```php
// Use services independently
$emailScore = $emailService->score('user@example.com');
if ($emailScore !== null) {
    echo "Email Fraud Score: {$emailScore->fraudScore}\n";
    echo "Valid: " . ($emailScore->valid ? 'Yes' : 'No') . "\n";
    echo "Disposable: " . ($emailScore->disposable ? 'Yes' : 'No') . "\n";
}

$ipScore = $ipService->score('8.8.8.8', 'Mozilla/5.0...');
if ($ipScore !== null) {
    echo "IP Fraud Score: {$ipScore->fraudScore}\n";
    echo "Country: {$ipScore->countryCode}\n";
    echo "VPN: " . ($ipScore->vpn ? 'Yes' : 'No') . "\n";
}
```

---

## Development Workflow

### Running PHP Syntax Checks

```bash
# Check all source files
find src -name "*.php" -exec php -l {} \;
```

### Code Style

This library follows:
- **PSR-4** autoloading
- **PSR-3** logging interface
- **PSR-16** simple cache interface
- **PHP 8.1+** features (strict types, constructor property promotion, readonly properties, enums, match expressions)

### Project Structure

```
ipqs-triscore/
├── src/                    # Source code (PSR-4: Kodegen\Ipqs)
├── vendor/                 # Composer dependencies
├── composer.json           # Package configuration
├── composer.lock           # Locked dependencies
├── LICENSE.md              # Dual MIT/Apache-2.0 license
└── README.md               # This file
```

---

## Tri-Risk Algorithm

### How It Works

1. **Score Collection** (Lines match Kotlin implementation):
   - Gather scores from Email, IP, Phone (use pre-fetched OR call services)
   - Pre-fetched scores take precedence
   - Missing channels are skipped gracefully

2. **Base Risk Calculation**:
   - Calculate average: `avgScore = floor(sumOfScores / scoredServices)`
   - Map to base risk:
     - `avgScore ≤ 50` → **LOW**
     - `avgScore 51-75` → **MEDIUM**
     - `avgScore ≥ 76` → **HIGH**
   - Special case: No scores → `avgScore=0`, **LOW**

3. **IP-Based Overrides** (only elevations, never downgrades):
   - **Override 1**: `LOW` + `ipFraudScore ≥ 75` → **MEDIUM**
   - **Override 2**: `MEDIUM` + `ipFraudScore ≥ 88` → **HIGH**

4. **Build Result**:
   - Populate metadata with IP geo and flags
   - Return `FraudEvaluationResult`

### Why IP Gets Special Treatment

1. **Technical sophistication** - Proxies/VPNs/Tor indicate deliberate obfuscation
2. **Real-time threat intelligence** - IP reputation changes rapidly
3. **Harder to spoof** - Email/phone can be stolen; IP behavior is harder to fake

---

## Configuration

### IpqsConfig Options

```php
$config = new IpqsConfig(
    apiKey: 'your_api_key',           // Required
    baseUrl: 'https://...',            // Optional, default: IPQS API endpoint
    timeout: 10,                       // Optional, default: 10 seconds
    strictness: 0,                     // Optional, 0-3 (default: 0)
);

// Or load from environment
$config = IpqsConfig::fromEnv();
```

### Strictness Levels

- `0` (default) - Balanced fraud detection
- `1` - Moderate strictness
- `2` - High strictness (more false positives)
- `3` - Maximum strictness (most aggressive)

---

## Cache Strategy

| Service | TTL | Rationale |
|---------|-----|-----------|
| Email | 90 days | User attributes change slowly |
| Phone | 90 days | Phone numbers rarely change |
| IP | **3 days** | IPs reassigned frequently, VPNs rotate |

**Performance Impact**: ~90% cache hit rate in production = ~90% cost reduction

---

## Error Handling

**Philosophy**: Never throw exceptions, always return null on errors

```php
// Services return null on API errors
$score = $emailService->score('invalid@domain');
if ($score === null) {
    // Handle error: API down, validation failed, etc.
    // System continues to work with other channels
}

// Tri-risk continues with available data
$result = $evaluator->evaluate(email: 'user@example.com'); // IP/Phone may fail
// Still returns a result based on available scores
```

---

## Framework Integration Examples

### Laravel

```php
// app/Providers/IpqsServiceProvider.php
use Kodegen\Ipqs\TriRisk\TriRiskEvaluator;

public function register()
{
    $this->app->singleton(TriRiskEvaluator::class, function ($app) {
        $config = IpqsConfig::fromEnv();
        $cache = $app->make('cache.store'); // Laravel cache
        $logger = $app->make('log');

        $emailClient = new EmailClient($config, $logger);
        $ipClient = new IpClient($config, $logger);
        $phoneClient = new PhoneClient($config, $logger);

        $emailService = new EmailQualityScoreService($emailClient, $cache, $logger);
        $ipService = new IpQualityScoreService($ipClient, $cache, $logger);
        $phoneService = new PhoneQualityScoreService($phoneClient, $cache, $logger);

        return new TriRiskEvaluator($emailService, $ipService, $phoneService);
    });
}

// Usage in controller
public function register(Request $request, TriRiskEvaluator $evaluator)
{
    $result = $evaluator->evaluate(
        email: $request->email,
        ipAddress: $request->ip(),
        userAgent: $request->userAgent(),
        phoneNumber: $request->phone
    );

    if ($result->riskCategory === RiskCategory::HIGH) {
        return response()->json(['error' => 'Registration blocked'], 403);
    }
}
```

### Symfony

```yaml
# config/services.yaml
services:
    Kodegen\Ipqs\Config\IpqsConfig:
        factory: ['Kodegen\Ipqs\Config\IpqsConfig', 'fromEnv']

    Kodegen\Ipqs\TriRisk\TriRiskEvaluator:
        arguments:
            $emailService: '@Kodegen\Ipqs\Service\EmailQualityScoreService'
            $ipService: '@Kodegen\Ipqs\Service\IpQualityScoreService'
            $phoneService: '@Kodegen\Ipqs\Service\PhoneQualityScoreService'
```

---

## License

This project is dual-licensed under your choice of:
- [MIT License](LICENSE.md#mit-license)
- [Apache License 2.0](LICENSE.md#apache-license-20)

See [LICENSE.md](LICENSE.md) for full text of both licenses.

---

## Credits

Ported from Kotlin implementation at [analytics.elitefintech.com](https://github.com/elitefintech/analytics)

**Author**: David Maple (david@kodegen.ai)

---

## Support

- **Issues**: [GitHub Issues](<repository-url>/issues)
- **API Documentation**: https://www.ipqualityscore.com/documentation/overview
- **IPQualityScore Dashboard**: https://www.ipqualityscore.com/user/login
