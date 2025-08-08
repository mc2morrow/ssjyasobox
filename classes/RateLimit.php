<?php
class RateLimit {
  private PDO $pdo;

  // โทษตามลำดับ: 5m, 15m, 30m, 1h, 1d
  private array $penalties = [300, 900, 1800, 3600, 86400];

  public function __construct(PDO $pdo) { $this->pdo = $pdo; }

  public function isIpLocked(string $ip): array {
    $stmt = $this->pdo->prepare("SELECT locked_until, strikes FROM ip_locks WHERE ip = :ip");
    $stmt->execute([':ip'=>$ip]);
    $row = $stmt->fetch();
    if (!$row) return ['locked'=>false, 'strikes'=>0];
    $locked = (strtotime($row['locked_until']) > time());
    return ['locked'=>$locked, 'strikes'=>(int)$row['strikes'], 'locked_until'=>$row['locked_until']];
  }

  public function recordAttempt(string $ip, bool $success): void {
    $stmt = $this->pdo->prepare("
      INSERT INTO register_attempts (ip, attempted_at, success)
      VALUES (:ip, NOW(), :success)
    ");
    $stmt->execute([':ip'=>$ip, ':success'=>$success ? 1 : 0]);
  }

  public function checkAndPenalizeIfNeeded(string $ip): ?array {
    $stmt = $this->pdo->prepare("
      SELECT COUNT(*) FROM register_attempts
      WHERE ip = :ip AND attempted_at >= (NOW() - INTERVAL 1 MINUTE)
    ");
    $stmt->execute([':ip'=>$ip]);
    $count = (int)$stmt->fetchColumn();

    if ($count > REGISTER_MAX_IN_MINUTE) {
      $state = $this->isIpLocked($ip);
      $currentStrikes = $state['strikes'] ?? 0;
      $nextStrike = min($currentStrikes + 1, count($this->penalties));
      $duration = $this->penalties[$nextStrike - 1];
      $lockedUntil = date('Y-m-d H:i:s', time() + $duration);

      $stmt = $this->pdo->prepare("
        INSERT INTO ip_locks (ip, locked_until, strikes) VALUES (:ip, :lu, :s)
        ON DUPLICATE KEY UPDATE locked_until = VALUES(locked_until), strikes = VALUES(strikes)
      ");
      $stmt->execute([':ip'=>$ip, ':lu'=>$lockedUntil, ':s'=>$nextStrike]);

      return ['locked'=>true, 'locked_until'=>$lockedUntil, 'strikes'=>$nextStrike, 'duration'=>$duration];
    }
    return null;
  }

  public function adminUnlock(string $ip): void {
    $stmt = $this->pdo->prepare("DELETE FROM ip_locks WHERE ip = :ip");
    $stmt->execute([':ip'=>$ip]);
  }
}
