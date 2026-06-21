<?php

$file = 'C:\\Users\\user\\Downloads\\UniServerZ\\www\\cocopay\\core\\app\\Http\\Controllers\\User\\LoanController.php';
$code = file_get_contents($file);

$code = preg_replace(
    "/class LoanController extends Controller\s*\{\s*/",
    "class LoanController extends Controller\n{\n    private function certificateStatus(): string\n    {\n        \$address = (array) auth()->user()->address;\n\n        return \$address['membership_certificate_status'] ?? 'missing';\n    }\n\n    private function ensureVerifiedCooperativeMember()\n    {\n        if (\$this->certificateStatus() === 'approved') {\n            return null;\n        }\n\n        session()->forget('loan');\n\n        \$notify[] = ['error', 'Loan access requires an approved NATCODEV membership certificate. Upload your certificate and wait for admin approval to become a verified cooperative member.'];\n        return to_route('user.profile.setting')->withNotify(\$notify);\n    }\n\n",
    $code,
    1
);

$code = str_replace(
    "    public function plans()\n    {\n        \$pageTitle = 'Loan Plans';",
    "    public function plans()\n    {\n        if (\$response = \$this->ensureVerifiedCooperativeMember()) {\n            return \$response;\n        }\n\n        \$pageTitle = 'Loan Plans';",
    $code
);

$code = str_replace(
    "    public function applyLoan(Request \$request, \$id)\n    {\n\n        \$plan = LoanPlan::active()->findOrFail(\$id);",
    "    public function applyLoan(Request \$request, \$id)\n    {\n        if (\$response = \$this->ensureVerifiedCooperativeMember()) {\n            return \$response;\n        }\n\n        \$plan = LoanPlan::active()->findOrFail(\$id);",
    $code
);

$code = str_replace(
    "    public function loanPreview()\n    {\n        \$loan = session('loan');",
    "    public function loanPreview()\n    {\n        if (\$response = \$this->ensureVerifiedCooperativeMember()) {\n            return \$response;\n        }\n\n        \$loan = session('loan');",
    $code
);

$code = str_replace(
    "    public function confirm(Request \$request)\n    {\n        \$loan = session('loan');",
    "    public function confirm(Request \$request)\n    {\n        if (\$response = \$this->ensureVerifiedCooperativeMember()) {\n            return \$response;\n        }\n\n        \$loan = session('loan');",
    $code
);

file_put_contents($file, $code);

echo "LOANS_GATED_BY_CERTIFICATE\n";
