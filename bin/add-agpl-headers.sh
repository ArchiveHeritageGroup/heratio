#!/bin/bash
# Script to add AGPL-3 headers to PHP files
# Usage: ./bin/add-agpl-headers.sh

echo "Adding AGPL-3 headers to Heratio packages..."
echo ""

count=0

# Process Services and Controllers
for file in $(grep -rL "Copyright (C) 2026" packages/ahg-*/src/Services/*.php packages/ahg-*/src/Controllers/*.php packages/ahg-*/src/Http/Controllers/*.php 2>/dev/null); do
    if [ -f "$file" ]; then
        # Get the class name from the file
        class=$(grep "^class " "$file" | awk '{print $2}' | head -1)
        
        # Determine type
        if [[ "$file" == *"Controllers"* ]]; then
            desc="${class} - Controller for Heratio"
        else
            desc="${class} - Service for Heratio"
        fi
        
        # Create the header
        file_header="/**
 * ${desc}
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plansailingisystems
 *
 * This file is part of Heratio.
 *
 * Heratio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Heratio is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Heratio. If not, see <https://www.gnu.org/licenses/>.
 */"
        
        # Check if file starts with <?php
        if grep -q "^<?php$" "$file"; then
            # Create temp file with new header
            {
                echo "<?php"
                echo ""
                echo "$file_header"
                echo ""
                sed -n '/^namespace /,$ p' "$file" | tail -n +2
            } > "${file}.tmp"
            
            mv "${file}.tmp" "$file"
            echo "Updated: $file"
            ((count++))
        fi
    fi
done

echo ""
echo "Updated $count files"
