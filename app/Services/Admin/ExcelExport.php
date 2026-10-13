<?php

namespace App\Services\Admin;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;

class ExcelExport
{
    public function __construct(private Queries $queries) {}

    private function grade(string $g): string
    {
        return ['P1' => '小学一年级', 'P2' => '小学二年级', 'P3' => '小学三年级', 'P4' => '小学四年级', 'P5' => '小学五年级', 'P6' => '小学六年级', 'J1' => '初一', 'J2' => '初二', 'J3' => '初三', 'S1' => '高一', 'S2' => '高二', 'S3' => '高三'][$g] ?? $g;
    }

    private function row(array $values): Row
    {
        return new Row(array_map(fn ($v) => is_int($v) || is_float($v) ? Cell::fromValue($v) : new StringCell((string) ($v ?? '')), $values));
    }

    private function orderByParentAndStudent(Builder $query, string $type): Builder
    {
        $student = DB::table('students as export_students')
            ->join('parents as export_parents', 'export_parents.id', '=', 'export_students.parent_id');
        if ($type === 'scores') {
            $student->join('exams as export_exams', 'export_exams.student_id', '=', 'export_students.id')
                ->whereColumn('export_exams.id', 'exam_subjects.exam_id');
        } else {
            $student->whereColumn('export_students.id', $type === 'students' ? 'students.id' : 'intentions.student_id');
        }

        // Sort before batching; the unique ID keeps equal phone/name rows stable.
        return $query->reorder()
            ->orderBy((clone $student)->select('export_parents.phone'))
            ->orderBy((clone $student)->select('export_students.name'))
            ->orderBy($query->getModel()->qualifyColumn('id'));
    }

    public function write(string $path, string $kind, array $filters): array
    {
        $writer = new Writer;
        $writer->openToFile($path);
        $counts = [];
        $first = true;
        try {
            // A consistent snapshot keeps multi-sheet exports internally coherent.
            DB::transaction(function () use ($writer, $kind, $filters, &$counts, &$first) {
                foreach ($kind === 'all' ? ['students', 'scores', 'intentions'] : [$kind] as $type) {
                    $sheet = $first ? $writer->getCurrentSheet() : $writer->addNewSheetAndMakeItCurrent();
                    $first = false;
                    $sheet->setName(['students' => '学生档案', 'scores' => '科目成绩', 'intentions' => '学校意向'][$type]);
                    $headers = match ($type) {
                        'students' => ['学生ID', '学生姓名', '性别', '当前学校', '入学年份', '入学年级', '当前年级', '联系手机号', '注册家长手机号', '考试批次数', '意向数'],'scores' => ['成绩ID', '注册家长手机号', '学生姓名', '考试名称', '考试日期', '考试时学校', '考试时年级', '科目', '记录方式', '分数', '满分', '得分率(%)', '等级', '考试备注'],'intentions' => ['意向ID', '注册家长手机号', '学生姓名', '当前学校', '当前年级', '意向学校', '意向等级', '备注']
                    };
                    $sheet->setColumnWidthForRange(20, 1, count($headers));
                    $sheet->setColumnWidth(40, 1);
                    $writer->addRow(Row::fromValuesWithStyle($headers, (new Style)->withFontBold(true)->withBackgroundColor('235E48')->withFontColor('FFFFFF')));
                    $counts[$type] = 0;
                    $query = $this->queries->$type($filters);
                    if ($kind === 'all') {
                        $query = $this->orderByParentAndStudent($query, $type);
                    }
                    foreach ($query->lazy(500) as $record) {
                        $values = match ($type) {
                            'students' => [$record->id, $record->name, ['male' => '男', 'female' => '女', 'unspecified' => '未填写'][$record->gender], $record->school, (int) $record->entry_year, $this->grade($record->entry_grade), $this->grade($record->current_grade), $record->contact_phone, $record->parent->phone, (int) $record->exams_count, (int) $record->intentions_count],
                            'scores' => [$record->id, $record->exam->student->parent->phone, $record->exam->student->name, $record->exam->title, $record->exam->exam_date->toDateString(), $record->exam->school_at_exam, $this->grade($record->exam->grade_at_exam), $record->name, $record->mode === 'score' ? '分数' : '等级', $record->mode === 'score' ? (float) $record->score : '', $record->mode === 'score' ? (float) $record->max_score : '', $record->mode === 'score' ? round((float) $record->score / (float) $record->max_score * 100, 2) : '', $record->grade, $record->exam->note],
                            'intentions' => [$record->id, $record->student->parent->phone, $record->student->name, $record->student->school, $this->grade($record->student->current_grade), $record->school, $record->level === 'first' ? '优先' : '备选', $record->note],
                        };
                        // Explicit string cells preserve phone/IDs and prevent formula execution.
                        $writer->addRow($this->row($values));
                        $counts[$type]++;
                    }
                }
            });
        } finally {
            $writer->close();
        }

        return $counts;
    }
}
