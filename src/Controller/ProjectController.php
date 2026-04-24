<?php

namespace App\Controller;

use App\Entity\Project;
use App\Repository\ProjectRepository;
use App\Enum\FlashType;
use App\Enum\MaterialType;
use App\Enum\UserRole;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Dompdf\Dompdf;

class ProjectController extends AbstractController
{
    private function validateProjectData(string $name, array $materials, int $workers, float $budget, ?string $start, ?string $finish): array
    {
        $errors = [];

        if (empty($name)) {
            $errors[] = 'Имя проекта не может быть пустым.';
        }

        if (empty($materials)) {
            $errors[] = 'Необходимо выбрать хотя бы один материал.';
        }

        if ($workers < 1) {
            $errors[] = 'Количество работников должно быть больше нуля.';
        }
        if ($budget < 0) {
            $errors[] = 'Бюджет не может быть отрицательным.';
        }
        if (empty($start) || empty($finish)) {
            $errors[] = 'Даты начала и конца проекта должны быть указаны.';
        } elseif (strtotime($start) >= strtotime($finish)) {
            $errors[] = 'Дата начала должна быть раньше даты завершения.';
        }

        return $errors;
    }

    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function index(Request $request, ProjectRepository $projectRepository): Response
    {
        if (!$this->getUser()) {
            return $this->redirectToRoute('app_login');
        }

        $searchName = trim($request->query->get('search_name', ''));
        $searchPlace = trim($request->query->get('search_place', ''));

        $projects = $projectRepository->searchProjects($searchName, $searchPlace);

        return $this->render('project/index.html.twig', [
            'projects' => $projects,
            'search_name' => $searchName,
            'search_place' => $searchPlace,
            'available_materials' => MaterialType::cases()
        ]);
    }

    #[Route('/project/add', name: 'app_project_add', methods: ['POST'])]
    public function store(Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->getUser()) {
            return $this->redirectToRoute('app_login');
        }

        $name = strip_tags(trim($request->request->get('project_name', '')));
        $place = strip_tags(trim($request->request->get('place', '')));
        $workers = filter_var($request->request->get('workers', 0), FILTER_VALIDATE_INT);
        $budget = filter_var($request->request->get('budget', 0), FILTER_VALIDATE_FLOAT);
        $materialsArr = $request->request->all('materials');
        $deadlineStart = $request->request->get('deadline_start');
        $deadlineFinish = $request->request->get('deadline_finish');

        $errors = $this->validateProjectData($name, $materialsArr, $workers, $budget, $deadlineStart, $deadlineFinish);

        if (!empty($errors)) {
            foreach ($errors as $error) {
                $this->addFlash(FlashType::ERROR->value, $error);
            }
            return $this->redirectToRoute('app_home', ['_fragment' => 'projects-table']);
        }

        $originalName = $name;
        $counter = 1;
        while ($em->getRepository(Project::class)->findOneBy(['projectName' => $name])) {
            $name = $originalName . " ($counter)";
            $counter++;
        }

        try {
            $project = new Project();
            $project->setProjectName($name);
            $project->setMaterials(implode(', ', array_map('strip_tags', $materialsArr)));
            $project->setWorkersCount($workers);
            $project->setBudget((string)$budget);
            $project->setPlace($place);
            $project->setDeadlineStart(new \DateTime($deadlineStart));
            $project->setDeadlineFinish(new \DateTime($deadlineFinish));
            $project->setCreateAt(new \DateTime());
            $project->setOwner($this->getUser());

            $em->persist($project);
            $em->flush();

            $this->addFlash(FlashType::SUCCESS->value, "Проект '$name' добавлен.");
        } catch (\Exception $ex) {
            $this->addFlash(FlashType::ERROR->value, 'Ошибка при сохранении проекта.');
        }

        return $this->redirectToRoute('app_home', ['_fragment' => 'projects-table']);
    }

    #[Route('/project/update', name: 'app_project_update', methods: ['POST'])]
    public function update(Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->getUser()) {
            return $this->redirectToRoute('app_login');
        }

        $projectId = $request->request->get('id');
        $project = $em->getRepository(Project::class)->find($projectId);

        if (!$project) {
            $this->addFlash(FlashType::ERROR->value, 'Проект не найден.');
            return $this->redirectToRoute('app_home', ['_fragment' => 'projects-table']);
        }

        $isOwner = $project->getOwner() === $this->getUser();
        $isAdmin = $this->isGranted(UserRole::ADMIN->value);

        if (!$isOwner && !$isAdmin) {
            throw $this->createAccessDeniedException();
        }

        $newName = strip_tags(trim($request->request->get('project_name', '')));
        $materialsArr = (array)$request->request->all('materials');
        $workers = (int)$request->request->get('workers_count', 0);
        $budget = (float)$request->request->get('budget', 0);
        $start = $request->request->get('deadline_start');
        $finish = $request->request->get('deadline_finish');
        $place = strip_tags(trim($request->request->get('place', '')));

        $errors = $this->validateProjectData($newName, $materialsArr, $workers, $budget, $start, $finish);

        if (!empty($errors)) {
            foreach ($errors as $error) {
                $this->addFlash(FlashType::ERROR->value, $error);
            }
            return $this->redirectToRoute('app_home', ['_fragment' => 'projects-table']);
        }

        try {
            if ($newName !== $project->getProjectName()) {
                $originalName = $newName;
                $counter = 1;
                while ($em->getRepository(Project::class)->findOneBy(['projectName' => $newName])) {
                    $newName = $originalName . " ($counter)";
                    $counter++;
                }
                $project->setProjectName($newName);
            }

            $project->setMaterials(implode(', ', array_map('strip_tags', $materialsArr)));
            $project->setWorkersCount($workers);
            $project->setBudget((string)$budget);
            $project->setPlace($place);
            $project->setDeadlineStart(new \DateTime($start));
            $project->setDeadlineFinish(new \DateTime($finish));

            $em->flush();
            $this->addFlash(FlashType::SUCCESS->value, 'Проект обновлен.');
        } catch (\Exception $ex) {
            $this->addFlash(FlashType::ERROR->value, 'Ошибка обновления.');
        }

        return $this->redirectToRoute('app_home', ['_fragment' => 'projects-table']);
    }

    #[Route('/project/delete', name: 'app_project_delete', methods: ['POST'])]
    public function delete(Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->getUser()) {
            return $this->redirectToRoute('app_login');
        }

        $projectId = $request->request->get('id');
        $project = $em->getRepository(Project::class)->find($projectId);

        if ($project) {
            $isOwner = $project->getOwner() === $this->getUser();
            $isAdmin = $this->isGranted(UserRole::ADMIN->value);

            if ($isOwner || $isAdmin) {
                $em->remove($project);
                $em->flush();
                $this->addFlash(FlashType::SUCCESS->value, 'Удалено.');
            }
        }

        return $this->redirectToRoute('app_home', ['_fragment' => 'projects-table']);
    }

    #[Route('/export/csv', name: 'app_export_csv', methods: ['GET'])]
    public function exportCsv(EntityManagerInterface $em): StreamedResponse
    {
        $projects = $em->getRepository(Project::class)->findAll();

        $response = new StreamedResponse(function () use ($projects) {
            $output = fopen('php://output', 'w');
            fputs($output, "\xEF\xBB\xBF");
            fputcsv($output, ['№', 'Name', 'Materials', 'Workers', 'Budget', 'Start', 'Finish', 'Place'], ';');

            $num = 1;
            foreach ($projects as $p) {
                fputcsv($output, [
                    $num++,
                    $p->getProjectName(),
                    $p->getMaterials(),
                    $p->getWorkersCount(),
                    $p->getBudget(),
                    $p->getDeadlineStart()?->format('Y-m-d'),
                    $p->getDeadlineFinish()?->format('Y-m-d'),
                    $p->getPlace()
                ], ';');
            }
            fclose($output);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="projects.csv"');

        return $response;
    }

    #[Route('/export/excel', name: 'app_export_excel', methods: ['GET'])]
    public function exportExcel(EntityManagerInterface $em): StreamedResponse
    {
        $projects = $em->getRepository(Project::class)->findAll();
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->fromArray(['№', 'Название', 'Материалы', 'Рабочие', 'Бюджет', 'Начало', 'Конец', 'Место'], NULL, 'A1');

        $row = 2;
        foreach ($projects as $p) {
            $sheet->setCellValue('A' . $row, $row - 1);
            $sheet->setCellValue('B' . $row, $p->getProjectName());
            $sheet->setCellValue('C' . $row, $p->getMaterials());
            $sheet->setCellValue('D' . $row, $p->getWorkersCount());
            $sheet->setCellValue('E' . $row, $p->getBudget());
            $sheet->setCellValue('F' . $row, $p->getDeadlineStart()?->format('d.m.Y'));
            $sheet->setCellValue('G' . $row, $p->getDeadlineFinish()?->format('d.m.Y'));
            $sheet->setCellValue('H' . $row, $p->getPlace());
            $row++;
        }

        $response = new StreamedResponse(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        });

        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->headers->set('Content-Disposition', 'attachment;filename="projects.xlsx"');

        return $response;
    }

    #[Route('/export/pdf', name: 'app_export_pdf', methods: ['GET'])]
    public function exportPdf(EntityManagerInterface $em): Response
    {
        $projects = $em->getRepository(Project::class)->findAll();
        $html = $this->renderView('pdf/export.html.twig', ['projects' => $projects]);

        $dompdf = new Dompdf(['isHtml5ParserEnabled' => true, 'isRemoteEnabled' => true]);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        return new Response(
            $dompdf->output(),
            Response::HTTP_OK,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="projects.pdf"'
            ]
        );
    }

    #[Route('/import/excel', name: 'app_import_excel', methods: ['POST'])]
    public function importExcel(Request $request, EntityManagerInterface $em): Response
    {
        $file = $request->files->get('excel_file');
        $allowDuplicates = $request->request->get('allow_duplicates') === '1';

        if (!$file || !$file->isValid()) {
            $this->addFlash(FlashType::ERROR->value, 'Файл не найден.');
            return $this->redirectToRoute('app_home', ['_fragment' => 'projects-table']);
        }

        $successCount = 0;
        try {
            $spreadsheet = IOFactory::load($file->getPathname());
            $rows = $spreadsheet->getActiveSheet()->toArray();
            $user = $this->getUser();

            for ($i = 1; $i < count($rows); $i++) {
                $row = $rows[$i];
                $line = $i + 1;

                if (empty($row[1])) continue;

                $name = trim($row[1]);
                $materialsRaw = trim($row[2] ?? '');
                $materialsArr = array_filter(array_map('trim', explode(',', $materialsRaw)));
                $workers = (int)str_replace([',', ' '], '', $row[3] ?? '0');
                $budget = (float)str_replace([',', ' '], '', $row[4] ?? '0');
                $start = $row[5] ?? null;
                $finish = $row[6] ?? null;
                $place = trim($row[7] ?? '');

                $errors = $this->validateProjectData($name, $materialsArr, $workers, $budget, $start, $finish);

                if (!empty($errors)) {
                    $this->addFlash(FlashType::IMPORT_ERRORS->value, "Строка $line: " . implode(' ', $errors));
                    continue;
                }

                if ($allowDuplicates) {
                    $originalName = $name;
                    $counter = 1;
                    while ($em->getRepository(Project::class)->findOneBy(['projectName' => $name])) {
                        $name = $originalName . " ($counter)";
                        $counter++;
                    }
                } else {
                    $existing = $em->getRepository(Project::class)->findOneBy([
                        'projectName' => $name,
                        'place' => $place
                    ]);
                    if ($existing) {
                        $this->addFlash(FlashType::IMPORT_ERRORS->value, "Строка $line: Проект '$name' в '$place' уже существует.");
                        continue;
                    }
                }

                try {
                    $project = new Project();
                    $project->setProjectName($name);
                    $project->setMaterials(implode(', ', $materialsArr));
                    $project->setWorkersCount($workers);
                    $project->setBudget((string)$budget);
                    $project->setDeadlineStart(new \DateTime($start));
                    $project->setDeadlineFinish(new \DateTime($finish));
                    $project->setPlace($place);
                    $project->setCreateAt(new \DateTime());
                    $project->setOwner($user);

                    $em->persist($project);
                    $em->flush();
                    $successCount++;
                } catch (\Exception $e) {
                    $this->addFlash(FlashType::IMPORT_ERRORS->value, "Строка $line: " . $e->getMessage());
                }
            }
            if ($successCount > 0) {
                $this->addFlash(FlashType::SUCCESS->value, "Импортировано строк: $successCount.");
            }
        } catch (\Exception $ex) {
            $this->addFlash(FlashType::ERROR->value, 'Ошибка при чтении файла: ' . $ex->getMessage());
        }

        return $this->redirectToRoute('app_home', ['_fragment' => 'projects-table']);
    }
}
