<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\DirectoryServiceInterface;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DirectoryController extends Controller
{
    public function __construct(
        private readonly DirectoryServiceInterface $directoryService,
    ) {}

    /**
     * Display the alumni directory with search, filter, and pagination.
     */
    public function index(Request $request): Response
    {
        $query = $request->input('q', '');
        $filters = [
            'batch' => $request->input('batch'),
            'role' => $request->input('role'),
            'tech_stack' => $request->input('tech_stack'),
            'experience_level' => $request->input('experience_level'),
        ];
        $page = (int) $request->input('page', 1);

        $results = $this->directoryService->searchAlumni($query, $filters, $page);

        return Inertia::render('Directory/Index', [
            'results' => $results,
            'filters' => [
                'q' => $query,
                'batch' => $filters['batch'],
                'role' => $filters['role'],
                'tech_stack' => $filters['tech_stack'],
                'experience_level' => $filters['experience_level'],
            ],
        ]);
    }

    /**
     * Show a single alumni profile (for modal display).
     */
    public function show(string $userId): Response
    {
        $profile = $this->directoryService->getAlumniProfile($userId);

        if (!$profile) {
            abort(404, 'Alumni profile not found.');
        }

        return Inertia::render('Directory/Show', [
            'alumni' => $profile,
        ]);
    }
}
