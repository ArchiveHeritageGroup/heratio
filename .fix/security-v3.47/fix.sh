#!/bin/bash
set -e

REPO="ArchiveHeritageGroup/atom-ahg-plugins"
BRANCH="main"

# Fix 1: CreateGrapTables.php
# Replace all $table->VARCHAR(N) COMMENT '...' with $table->char(N)->charset('latin1')->collation('latin1_general_ci')
# Also replace bare $table->VARCHAR(N) without chain
sed -i \
  "s/\$table->VARCHAR(81) COMMENT 'recognition_status, recognized, not_recognized, pending, not_assessed'->default('not_assessed');/\$table->char(81)->charset('latin1')->collation('latin1_general_ci')->default('not_assessed');/" \
  .fix/security-v3.47/CreateGrapTables.php

sed -i \
  "s/\$table->VARCHAR(69) COMMENT 'measurement_basis, cost, fair_value, deemed_cost, nominal'->nullable();/\$table->char(69)->charset('latin1')->collation('latin1_general_ci')->nullable();/" \
  .fix/security-v3.47/CreateGrapTables.php

sed -i \
  "s/\$table->VARCHAR(112) COMMENT 'transaction_type, acquisition, revaluation, impairment, disposal, transfer, depreciation, correction';/\$table->char(112)->charset('latin1')->collation('latin1_general_ci');/" \
  .fix/security-v3.47/CreateGrapTables.php

sed -i \
  "s/\$table->VARCHAR(46) COMMENT 'check_type, full, quick, automated';/\$table->char(46)->charset('latin1')->collation('latin1_general_ci');/" \
  .fix/security-v3.47/CreateGrapTables.php

# Fix 2: CreateSpectrumTables.php
sed -i \
  "s/\$table->VARCHAR(41) COMMENT 'loan_type, incoming, outgoing';/\$table->char(41)->charset('latin1')->collation('latin1_general_ci');/" \
  .fix/security-v3.47/CreateSpectrumTables.php

sed -i \
  "s/\$table->VARCHAR(77) COMMENT 'status, requested, approved, active, overdue, returned, cancelled'->default('requested');/\$table->char(77)->charset('latin1')->collation('latin1_general_ci')->default('requested');/" \
  .fix/security-v3.47/CreateSpectrumTables.php

sed -i \
  "s/\$table->VARCHAR(70) COMMENT 'label_type, object, storage, exhibition, loan, qr, barcode';/\$table->char(70)->charset('latin1')->collation('latin1_general_ci');/" \
  .fix/security-v3.47/CreateSpectrumTables.php

# Fix 3: ahgSpectrumConditionReportJob.class.php - embedded <?php inside string
# The offending line is:
# <style <?php $n = sfConfig::get('csp_nonce', ''); echo $n ? preg_replace('/^nonce=/', 'nonce="', $n).'"' : ''; ?>>
# This needs to be restructured to not embed PHP inside a string
# Replace the block using a sentinel approach
python3 -c "
import re
with open('.fix/security-v3.47/ahgSpectrumConditionReportJob.class.php', 'r') as f:
    content = f.read()

# The problematic pattern - PHP embedded in string. Replace with a safe version.
# The CSP nonce should be set as a PHP variable before the string, then interpolated.
old = '''    <style <?php \$n = sfConfig::get('csp_nonce', ''); echo \$n ? preg_replace('/^nonce=/', 'nonce=\"', \$n).'\"' : ''; ?>>'''
new = '''    <style nonce=\"<?php echo htmlspecialchars(sfConfig::get('csp_nonce', '')); ?>\">'''
content = content.replace(old, new)

with open('.fix/security-v3.47/ahgSpectrumConditionReportJob.class.php', 'w') as f:
    f.write(content)
"

echo "Patches applied. Verifying syntax..."
php -l .fix/security-v3.47/CreateGrapTables.php && echo "CreateGrapTables.php: OK"
php -l .fix/security-v3.47/CreateSpectrumTables.php && echo "CreateSpectrumTables.php: OK"
php -l .fix/security-v3.47/ahgSpectrumConditionReportJob.class.php && echo "ahgSpectrumConditionReportJob.class.php: OK"
