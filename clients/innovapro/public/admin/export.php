<?php
declare(strict_types=1);
require __DIR__ . '/../../src/admin.php';
use function Innova\{db,filters,csv_cell,local_date};
[$where, $params] = filters();
$query = db()->prepare('SELECT * FROM leads' . $where . ' ORDER BY id DESC'); $query->execute($params);
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="innovapro-leads-' . gmdate('Y-m-d') . '.csv"');
$output = fopen('php://output', 'w'); fwrite($output, "\xEF\xBB\xBF");
$columns = ['id','created_at','nombre','telefono','email','utm_source','utm_medium','utm_campaign','utm_term','utm_content','gclid','wbraid','gbraid','landing_url','referrer','consent_rgpd','consent_version','email_status','email_attempts','email_last_error','email_sent_at'];
fputcsv($output, $columns, ';', '"', '');
while ($row = $query->fetch()) {
    $row['created_at'] = local_date($row['created_at']);
    $cells = array_map(static fn($column) => csv_cell($row[$column]), $columns);
    fputcsv($output, $cells, ';', '"', '');
}
fclose($output);
