import sys
import json
from pathlib import Path
from graphify.extract import extract
from graphify.build import build_from_json
from graphify.cluster import cluster, score_all
from graphify.analyze import god_nodes, surprising_connections, suggest_questions
from graphify.report import generate
from graphify.export import to_json

def run():
    Path('graphify-out').mkdir(exist_ok=True)
    code_files = ['clearcache.php', 'config.php', 'cron_cleanup.php', 'dashboard.php', 'index.php', 'logout.php', 'api/api_auth.php', 'api/api_get_courses.php', 'api/api_submit_baoluu.php', 'api/api_submit_huyhocphan.php', 'api/api_submit_letotnghiep.php', 'api/api_update_profile.php', 'assets/main.js', 'core/ApiHandler.php', 'core/BaoLuuService.php', 'core/CacheManager.php', 'core/ConfigService.php', 'core/DriveUploader.php', 'core/GoogleSheetClient.php', 'core/HuyHocPhanService.php', 'core/LeTotNghiepService.php', 'core/NotificationService.php', 'core/Response.php', 'core/Security.php', 'core/StudentRepository.php', 'includes/footer.php', 'includes/header.php', 'includes/sidebar.php', 'includes/components/empty_state.php', 'includes/components/file_upload.php', 'includes/components/progress_bar.php', 'includes/components/tabs_nav.php', 'includes/helpers/UIHelper.php', 'pages/form_baoluu.php', 'pages/form_huyhocphan.php', 'pages/form_letotnghiep.php', 'pages/thongtin_canhan.php']
    
    paths = [Path(f) for f in code_files if Path(f).exists()]
    
    print("Extracting AST...")
    result = extract(paths, cache_root=Path('.'))
    Path('graphify-out/.graphify_extract.json').write_text(json.dumps(result, indent=2))
    
    print(f"Building graph with {len(result['nodes'])} nodes...")
    G = build_from_json(result)
    
    print("Clustering...")
    communities = cluster(G)
    cohesion = score_all(G, communities)
    
    print("Analyzing...")
    gods = god_nodes(G)
    surprises = surprising_connections(G, communities)
    labels = {cid: f'Community {cid}' for cid in communities}
    questions = suggest_questions(G, communities, labels)
    
    detection = {
        "files": {"code": code_files},
        "total_files": len(code_files),
        "total_words": 12000
    }
    
    tokens = {'input': 0, 'output': 0}
    
    report = generate(G, communities, cohesion, labels, gods, surprises, detection, tokens, str(Path('.').absolute()), suggested_questions=questions)
    Path('graphify-out/GRAPH_REPORT.md').write_text(report)
    to_json(G, communities, 'graphify-out/graph.json')
    
    print("Done! Graph report saved to graphify-out/GRAPH_REPORT.md")

if __name__ == '__main__':
    run()
