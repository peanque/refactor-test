<?php

namespace DTApi\Helpers;

use DTApi\Repository\BookingRepository;
use Illuminiate\Database\Eloquent\Model;
use Exception;

// resend notification helper
if (!function_exists('resend_notification')) {

    function resend_notification(int $jobId,BookingRepository $bookingRepository, string $type='push')
    {
        try {
            $job = $bookingRepository->find($jobId);
            $jobData = $bookingRepository->jobToData($job);

            if ($type === 'push') {
                $bookingRepository->sendNotificationTranslator($job, $jobData, '*');
                $message = 'Push notification sent successfully';
            } else {
                $bookingRepository->sendSMSNotificationToTranslator($job);
                $message = 'SMS sent successfully';
            }

            return [
                'success' => true,
                'message' => $message,
                'status' => Response::HTTP_OK 
            ];
        } catch (ModelNotFoundException $ex) {
            return [
                'status' => Response::HTTP_NOT_FOUND,
                'message' => 'Job not found',
                'error' => $ex->getMessage(),
                'success' => false
            ];
        } catch (Exception $ex) {
            return [
                'status' => Response::HTTP_INTERNAL_SERVER_ERROR,
                'message' => ($type === 'push') ? 'Failed to send notification' : 'SMS notification failed',
                'error' => $ex->getMessage(),
                'success' => false
            ];
        }
    }
}

if (!function_exists('validate_notification_type')) {
    function validate_notification_type(string $type): bool
    {
        return in_array(strtolower($type), ['push', 'sms']);
    }
}