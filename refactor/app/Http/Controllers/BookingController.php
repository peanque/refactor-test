<?php

namespace DTApi\Http\Controllers;

use DTApi\Enums\UserType;
use DTApi\Models\Job;
use DTApi\Http\Requests;
use DTApi\Http\Resources\JobResource;
use DTApi\Models\Distance;
use Illuminate\Http\Request;
use DTApi\Repository\BookingRepository;
use Exception;
use Illuminate\Support\Facades\DB;
use DTApi\Helpers\NotficationHelper;

use function DTApi\Helpers\resend_notification;

/**
 * Class BookingController
 * @package DTApi\Http\Controllers
 */
class BookingController extends Controller
{


    /**
     * Variable Declaration
     */
    protected const PER_PAGE = 10; // initial pages to display in pagination

    /**
     * @var BookingRepository
     */
    protected $bookingRepository;

    /**
     * BookingController constructor.
     * @param BookingRepository $bookingRepository
     */
    public function __construct(BookingRepository $bookingRepository)
    {
        $this->bookingRepository = $bookingRepository;
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function index(Request $request): JsonResponse
    {
        // validate input to properly handle errors
        $validated = $request->validate([
            'user_id' => 'nullable|integer|exists:users,id',
        ]);

        $userId = $validated['user_id'] ?? null;
        $authUser = auth()->user(); // retrieve the authenticated user

        try {
            if ($userId) {
                $jobs = $this->bookingRepository->getUsersJobs($userId);
            } elseif(in_array($authUser->user_type, [UserType::ADMIN_ROLE_ID, UserType::SUPERADMIN_ROLE_ID])) {
                $jobs = $this->bookingRepository->getAll($request);
            } else {
                return $this->respondWithError(
                    'Accessing this daa is forbidden',
                    'Forbidden Access',
                    Response::HTTP_FORBIDDEN
                );
            }

            return $this->respondSuccessful(
                JobResource::collection($jobs),
                'Jobs successfully retrieved'
            );

        } catch (Exception $ex) {
            return $this->respondWithError(
                'Failed to retrieve records.',
                $ex->getMessage()
            );
        }
    }

    /**
     * @param $id
     * @return mixed
     * 
     * Implement route model binding
     */
    public function show(Job $job): JsonResponse
    {
        try {
            // eager load the relationship for translatorJobRel
            $job->load('translatorJobRel.user');
        } catch (ModelNotFoundException $ex) {
            return $this->respondWithError(
                'Record not found',
                $ex->getMessage(),
                Response::HTTP_NOT_FOUND
            );
        } catch (Exception $ex) {
            return $this->respondWithError(
                'Failed to retrieve any record',
                $ex->getMessage(),
                Response::HTTP_NOT_FOUND
            );
        }
        
        return $this->respondSuccessful(
            JobResource::make($job),
            'Record retrieved successfully'
        );
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function store(StoreBookingRequest $request): JsonResponse
    {
        // get the validated data
        $data = $request->validated();
        $user = auth()->user();

        try {
            $job = $this->bookingRepository->store($user, $data);
        } catch (Exception $ex) {
            return $this->respondWithError(
                'Record creation failed',
                $ex->getMessage(),
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        return $this->respondSuccessful(
            JobResource::make($job),
            'Record created successfully',
            Response::HTTP_CREATED
        );

    }

    /**
     * @param $id
     * @param Request $request
     * @return mixed
     */
    public function update(UpdateBookingRequest $request, Job $job): JsonResponse
    {
        $user = auth()->user();
        $job->update($request->validated());
        
        try {
            $job = $this->bookingRepository->updateJob($id, array_except($data, ['_token', 'submit']), $user);
        } catch (Exception $ex) {
            return $this->respondWithError(
                'Record update failed',
                $ex->getMessage(),
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        return $this->respondSuccessful(
            JobResource::make($job),
            'Record updated successfully'
        );
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function immediateJobEmail(JobEmailRequest $request): JsonResponse
    {
        try {
            $job = $this->bookingRepository->storeJobEmail($request->validated());
        } catch (Exception $ex) {
            return $this->respondWithError(
                'Record update failed',
                $ex->getMessage(),
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        // return job object after update job email
        return $this->respondSuccessful(
            JobResource::make($job),
            'Record updated successfully'
        );
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function getHistory(Request $request): JsonResponse
    {
        $userId = $request->get('user_id');
        if($userId) {
            $response = $this->bookingRepository->getUsersJobsHistory($userId, $request);
            return $this->respondSuccessful(

            )
            return response()->json($response);
        }
        // return an empty array
        return response()->json([]);
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function acceptJob(AcceptJobRequest $request): JsonResponse
    {
        $data = $request->validated();
        $user = auth()->user();

        $response = $this->bookingRepository->acceptJob($data, $user);

        return response()->json($response);
    }

    public function acceptJobWithId(int $id): JsonResponse
    {
        $user = auth()->user();

        $response = $this->bookingRepository->acceptJobWithId($id, $user);

        return response()->json($response);
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function cancelJob(int $jobId): JsonResponse
    {
        $user = $request->__authenticatedUser;

        $response = $this->bookingRepository->cancelJobAjax($jobId, $user);

        return response()->json($response);
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function endJob(int $jobId): JsonResponse
    {
        $response = $this->repository->endJob($jobId);

        return response()->json($response);

    }

    public function customerNotCall(int $jobId): JsonResponse
    {
        $response = $this->bookingRepository->customerNotCall($jobId);

        return response()->json($response);
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function getPotentialJobs(Request $request): JsonResponse
    {
        $data = $request->all();
        $user = $request->__authenticatedUser;

        $response = $this->repository->getPotentialJobs($user);

        return response()->json($response);
    }

    public function distanceFeed(DistanceFeedRequest $request): JsonResponse
    {
        $data = $request->validated();

        // try to use null coalescing operator
        $distance = $data['distance'] ?? "";
        $time = $data['time'] ?? "";
        $jobId = $data['jobid'] ?? "";
        $session = $data['session_time'] ?? "";

        // create a function for more readable code.
        $jobAttributes = $this->checkJobAttributes($data);
        $flagged = $jobAttributes['flagged'];
        $manuallyHandled = $jobAttributes['manually_handled'];
        $byAdmin = $jobAttributes['by_admin'];

        $adminComment = $data['admincomment'] ?? "";

        DB::beginTransaction();
        try {
            // if time and distance has value then update distance model
            if ($time || $distance) {
                $distanceUpdated = Distance::where('job_id', '=', $jobId)->update(array('distance' => $distance, 'time' => $time));
            }

            if ($adminComment || $session || $flagged || $manuallyHandled || $byAdmin) {

                $jobUpdated = Job::where('id', '=', $jobId)->update(
                    array('admin_comments' => $adminComment, 
                    'flagged' => $flagged, 
                    'session_time' => $session, 
                    'manually_handled' => $manuallyHandled, 
                    'by_admin' => $byAdmin)
                );
            }

            DB::commit();
        } catch (Exception $ex) {
            DB::rollBack();
            return response()->json([
                'message' => 'Distance or Job records cannot be updated',
                'error' => $ex->getMessage(),
                'success' => false
            ]);
        }

        return response()->json('Record updated!');
    }

    public function reopen(Request $request): JsonResponse
    {
        $data = $request->all();
        $response = $this->bookingRepository->reopen($data);

        return response()->json($response, Response::HTTP_OK);
    }

    public function resendNotifications(int $jobId)
    {
        $result = resend_notification($jobId, $this->bookingRepository, 'push');

        return response()->json($result, $result['status']);
    }

    /**
     * Sends SMS to Translator
     * @param Request $request
     * @return \Illuminate\Contracts\Routing\ResponseFactory|\Symfony\Component\HttpFoundation\Response
     */
    public function resendSMSNotifications(int $jobId)
    {
        $result = resend_notification($jobId, $this->bookingRepository, 'sms');

        return response()->json($result, $result['status']);
    }

    private function checkJobAttributes(array $data): array
    {
        $jobAttributes = [
            'flagged' => $this->checkBooleanAttributes($data, 'flagged'),
            'manually_handled' => $this->checkBooleanAttributes($data, 'manually_handled'),
            'by_admin' => $this->checkBooleanAttributes($data, 'by_admin')
        ];

        if ($jobAttributes['flagged'] === 'yes' && empty($data['admincomment'])) {
            return "Please, add comment";
        }

        return $jobAttributes;
    }

    private function checkBooleanAttributes(array $data, string $attribute): string
    {
        if (isset($data[$attribute]) && $data[$attribute] === 'true')
        {
            return 'yes';
        } else {
            return 'no';
        }
    }

}
