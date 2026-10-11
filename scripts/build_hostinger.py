#!/usr/bin/env python3
"""Build an allowlisted upload archive; never include local credentials or textbooks."""
import argparse
from pathlib import Path
from zipfile import ZipFile, ZIP_DEFLATED

ROOT=Path(__file__).resolve().parents[1]

def build(output):
    entries=[]
    for name in ('index.html','app.css','favicon.svg','core.js','lessons.js','book.js','interaction.js','student-auth.js','extras.js','practice.js'):
        entries.append((ROOT/'frontend'/name,'public_html/'+name))
    for name in ('index.php','.htaccess'):
        entries.append((ROOT/'deploy/hostinger'/name,'public_html/'+name))
    for name in ('bootstrap.php','auth.php','private_state.php','fractions.php','practice.php','learning.php','lessons.php','tutor.php','curriculum_library.php','deployment_check.php','initialize_database.php','api/index.php'):
        entries.append((ROOT/'php'/name,'chalo-private/php/'+name))
    entries.extend([(ROOT/'data/curriculum_sources.json','chalo-private/data/curriculum_sources.json'),
                    (ROOT/'data/lesson_plans.json','chalo-private/data/lesson_plans.json'),
                    (ROOT/'data/telangana_syllabus.json','chalo-private/data/telangana_syllabus.json'),
                    (ROOT/'.env.hostinger.example','chalo-private/.env.example'),
                    (ROOT/'HOSTINGER_DEPLOYMENT.md','DEPLOYMENT.md')])
    # SQL is deliberately separate from the upload archive: no automatic production DDL.
    for source,_ in entries:
        if not source.is_file() or source.is_symlink():
            raise ValueError(f'Missing or unsafe source: {source.name}')
    output=Path(output)
    output.parent.mkdir(parents=True,exist_ok=True)
    with ZipFile(output,'w',ZIP_DEFLATED) as archive:
        for source,target in entries:
            archive.write(source,target)
    return output

if __name__=='__main__':
    parser=argparse.ArgumentParser()
    parser.add_argument('--output',required=True)
    print(build(parser.parse_args().output))
