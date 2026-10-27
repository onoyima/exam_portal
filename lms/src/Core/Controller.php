<?php
namespace Lms\Core;

abstract class Controller
{
    protected Request $request;
    protected Response $response;

    public function __construct()
    {
        $this->request  = new Request();
        $this->response = new Response();
    }

    public function setRequest(Request $request): void
    {
        $this->request = $request;
    }

    public function setResponse(Response $response): void
    {
        $this->response = $response;
    }

    protected function validate(array $data, array $rules, array $messages = []): array
    {
        $validator = Validator::make($data, $rules, $messages);
        if ($validator->fails()) {
            $this->response->validationError($validator->errorMessages());
        }
        return $validator->validated();
    }

    protected function paginatedQuery(callable $queryBuilder): void
    {
        $page    = max(1, (int) $this->request->query('page', 1));
        $perPage = min(
            (int) $this->request->query('per_page', lms_config('pagination.per_page', 20)),
            lms_config('pagination.max_per_page', 100)
        );

        $result = $queryBuilder($page, $perPage);
        $this->response->paginated(
            $result['items'],
            $result['total'],
            $result['page'],
            $result['per_page']
        );
    }

    protected function user(): ?array
    {
        return $this->request->user();
    }

    protected function userId(): ?int
    {
        return $this->request->userId();
    }

    protected function userRole(): ?string
    {
        return $this->request->userRole();
    }
}
