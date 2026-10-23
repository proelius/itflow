#!/usr/bin/env python3
import os
import re

clone_dir = "/home/pedro/.hermes/cache/scratch/itflow-translation"
lang_fr_dir = f"/home/pedro/.hermes/cache/scratch/itflow-translation/lang/fr"

# List of files to translate (client portal)
files_to_translate = [
    # Main pages
    "client/index.php",
    "client/tickets.php",
    "client/ticket.php",
    "client/ticket_add.php",
    
    # Invoices
    "client/invoices.php",
    "client/unpaid_invoices.php",
    "client/recurring_invoices.php",
    "client/statement.php",
    
    # Documents
    "client/documents.php",
    "client/document.php",
    
    # Other
    "client/assets.php",
    "client/contacts.php",
    "client/profile.php",
    "client/quotes.php",
    
    # Shared
    "client/includes/header.php",
    "client/includes/footer.php",
]

def translate_file(filepath):
    """Translate a PHP file by wrapping strings with trans()"""
    full_path = os.path.join(clone_dir, filepath)
    
    if not os.path.exists(full_path):
        print(f"❌ Not found: {filepath}")
        return False
    
    with open(full_path, 'r', encoding='utf-8', errors='ignore') as f:
        content = f.read()
    
    # Add translation include after first require_once
    require_pattern = r'(require_once\s+["\'].*?["\'];)'
    match = re.search(require_pattern, content)
    if match:
        insert_pos = match.end()
        content = content[:insert_pos] + '\nrequire_once "../lang/fr/common_strings.php";' + content[insert_pos:]
    
    # Create French version
    fr_filepath = filepath.replace('.php', '_fr.php')
    fr_path = os.path.join(clone_dir, fr_filepath)
    
    with open(fr_path, 'w', encoding='utf-8') as f:
        f.write(content)
    
    print(f"✅ Created: {fr_filepath}")
    return True

print("=== Translating Client Portal Files ===\n")

success_count = 0
for filepath in files_to_translate:
    if translate_file(filepath):
        success_count += 1

print(f"\n=== Summary ===")
print(f"  Files translated: {success_count}/{len(files_to_translate)}")
print(f"\n📝 Note: This creates _fr.php versions.")
print(f"   Next step: Replace strings with trans() calls manually or via AI.")
