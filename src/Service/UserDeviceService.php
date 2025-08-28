<?php

namespace FastFast\Common\Service;

use App\Models\UserDevice;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class UserDeviceService
{
  /**
   * @param  int   $userId
   * @param  array $data [
   *   'device_token'    => string,   // required
   *   'device_type'     => 'ios'|'android', // required
   *   'device_id'       => ?string,
   *   'is_notification_authorized' => ?bool
   * ]
   * @return \App\Models\UserDevice
   */
  public function registerDevice(int $user_id, array $data): UserDevice | null
  {
    $device_token    = $data['device_token'] ?? null;
    $device_type     = $data['device_type'] ?? null;
    $is_notification_authorized = true;
    $device_id       = $data['device_id'] ?? null;


    if (array_key_exists('is_notification_authorized', $data) && $data['is_notification_authorized'] !== '') {
      $val = $data['is_notification_authorized'];

      $is_notification_authorized = is_bool($val)
        ? $val
        : filter_var($val, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

      // If it couldn't be parsed (NULL), keep default true
      if ($is_notification_authorized === null) {
        $is_notification_authorized = true;
      }
    }


    $device_type = strtolower((string) $device_type);
    if (!in_array($device_type, ['ios', 'android'], true)) {
      $device_type = null;
    }

    if (empty($device_token) || empty($device_type)) {
      return null;
    }

    return DB::transaction(function () use ($user_id, $device_token, $device_type, $device_id, $is_notification_authorized) {
      $byDeviceId = $device_id ? UserDevice::withTrashed()->where('device_id', $device_id)->first() : null;
      $byToken = UserDevice::withTrashed()->where('device_token', $device_token)->first();


      $device = $byDeviceId ?? $byToken ?? null;
      if ($byDeviceId && $byToken && $byDeviceId->id !== $byToken->id) {
        // Two different records found, delete one
        $byToken->forceDelete();
        $device = $byDeviceId;
      }

      if (is_null($device)) {
        $device = new UserDevice();
      }

      $device->user_id   = $user_id;
      $device->device_token = $device_token;
      $device->device_type  = $device_type;
      $device->device_id    = $device_id;
      $device->notification_enabled = true;
      $device->is_notification_authorized = $is_notification_authorized;

      if ($device->exists  && method_exists($device, 'trashed') && $device->trashed()) {
        $device->restore();
      }

      $device->save();

      return $device;
    });
  }

  /**
   * Disable a single device (logout/mute) so it stops receiving pushes.
   * @param  int         $userId
   * @param  string|null $installationId
   * @param  string|null $deviceToken
   * @return int Updated rows count
   */
  public function disableUserDevice(int $userId, ?string $device_id = null, ?string $device_token = null): int
  {
    if (empty($device_id) && empty($device_token)) {
      return 0;
    }
    return DB::transaction(function () use ($userId, $device_id, $device_token) {
      $q = UserDevice::where('user_id', $userId)->where('notification_enabled', true)->where(function ($q) use ($device_id, $device_token) {
        if (!empty($device_id)) {
          $q->orWhere('device_id', $device_id);
        }
        if (!empty($device_token)) {
          $q->orWhere('device_token', $device_token);
        }
      });

      return $q->update([
        'notification_enabled' => false,
      ]);
    });
  }

  function getUserstokens(int $userId): array
  {
    $groups = [
      'ios'     => [],
      'android' => [],
    ];
    $rows = UserDevice::query()
      ->where('user_id', $userId)
      ->where('notification_enabled', true)
      ->get(['device_type', 'device_token']);

    foreach ($rows as $row) {
      $type = strtolower((string) $row->device_type);

      if (($type === 'ios' || $type === 'android') && !empty($row->device_token)) {
        // Use the token as the key to de-dupe within each bucket
        $groups[$type][$row->device_token] = true;
      }
    }

    // 2) Legacy fallback (users.device_token / users.device_type)
    $user = User::find($userId);
    if ($user && !empty($user->device_token) && !empty($user->device_type)) {
      $legacyType = strtolower((string) $user->device_type);

      if ($legacyType === 'ios' || $legacyType === 'android') {
        $groups[$legacyType][$user->device_token] = true;
      }
    }

    // Convert de-dupe maps to arrays
    $groups['ios']     = array_keys($groups['ios']);
    $groups['android'] = array_keys($groups['android']);
    return $groups;
  }


    public function getTokens(User $user) {
        $tokens = [
            'ios' => [],
            'android' => []
        ];
        UserDevice::query()->where('user_id', $user->id)->get()->each(function ($device) use(&$tokens){
            if ($device->device_token) {
                $tokens[$device->device_type][] = $device->device_token;
            }
        });

        if (!empty($user->device_token) && !empty($user->device_type)) {
            $legacyType = strtolower((string) $user->device_type);

            if ($legacyType === 'ios' || $legacyType === 'android') {
                $tokens[$legacyType][] = $user->device_token;
            }
        }
        return [
            'type' => $user->user_type == 1 ? 'customer' : ($user->user_type == 3 ? 'rider' : 'seller'),
            'tokens' => $tokens,
        ];
    }

    public function getUsersDeviceTokens($users)
    {
        $tokens = [];
        UserDevice::query()->whereIn('user_id', $users->pluck('id'))->get()->each(function ($device) use (&$tokens) {
            if (!isset($tokens[$device->user_id])) {
                $user = $device->user;
                $tokens[$device->user_id] = [
                    'id' => $device->user_id,
                    'user_type' => $user->user_type == 1 ? 'customer' : ($user->user_type == 3 ? 'rider' : 'seller'),
                    'ios' => [],
                    'android' => []
                ];
            }

            if ($device->device_type === 'ios' && !empty($device->device_token)) {
                $tokens[$device->user_id]['ios'][] = $device->device_token;
            } else if ($device->device_type === 'android' && !empty($device->device_token)) {
                $tokens[$device->user_id]['android'][] = $device->device_token;
            }
        });

        return collect(array_values($tokens));
    }
}
