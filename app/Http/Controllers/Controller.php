<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

abstract class Controller
{
    /**
     * 404 a request for a page past the end of a paginated listing.
     *
     * paginate() returns an empty collection for an out-of-range page rather
     * than failing, so `/work?page=12` on an 11-page archive answered 200 with
     * an empty state reading "No pieces match this combination of filters" —
     * wrong, because no filters were applied. Worse, it made an unbounded set
     * of empty pages indexable, since `?page=` accepts any integer.
     *
     * Only page > 1 with nothing on it is out of range. Page 1 of a filter
     * combination that genuinely matches nothing is a real, useful empty state
     * and stays a 200.
     */
    protected function abortIfPastLastPage(LengthAwarePaginator $paginator): LengthAwarePaginator
    {
        if ($paginator->currentPage() > 1 && $paginator->isEmpty()) {
            throw new NotFoundHttpException('Page past the end of the listing.');
        }

        return $paginator;
    }
}
