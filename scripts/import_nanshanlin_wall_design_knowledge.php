<?php

use App\Models\KnowledgeBase;
use App\Models\Task;
use App\Services\GeoFlow\KnowledgeChunkSyncService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$sourceName = '背景墙设计尺寸.docx';
$effectiveDate = '2026-07-18';
$docPath = base_path('docs/南山林-背景墙设计尺寸规范入库稿-v1.md');

$designBlock = <<<'MD'

<!-- GEOFLOW-IMPORT:WALL-DESIGN-SIZE-V1:START -->

## 南山林内部背景墙设计尺寸与风格表达规范 v1

来源类型：南山林内部设计约定
来源文件：背景墙设计尺寸.docx
适用范围：背景墙设计、床头背景墙、护墙板配色、木饰面搭配、灯光色温、风格表达、设计避坑。
可信等级：内部经验可用，不作为国家标准、行业标准或强制规范。
生成边界：可以用于“建议、常见做法、设计参考、避坑提醒”，不得写成“国家规定、行业强制标准、唯一正确做法”。

### 1. 背景墙基础尺寸与灯光建议

背景墙灯光色温建议控制在 3000K-4000K 区间，适合营造温暖、柔和、稳定的居住氛围。生成文章时可以表达为“常见家装背景墙会优先考虑 3000K-4000K 的暖白光或中性暖光”，不要表达为“必须使用某个固定色温”。

床头背景墙可采用上下分区的设计方式。下半部分墙裙高度建议参考 1100-1200mm 区间，上半部分可根据墙面宽度、床体比例和视觉中心，采用 300mm、600mm、1000mm 等常见宽度的墙板模块进行组合，并可搭配 130mm 左右的格栅、石纹饰面、艺术画面或装饰板材形成拼接造型。

线条和收口可根据整体风格选择同色内工字线、同色外工字线、同色圆弧收口、同色直角收口、同色侧光灯带线、同色错层灯带线或同色异形型材。文章表达时重点强调“线条与收口决定背景墙的完成度”，不要只写板材颜色。

### 2. 奶油风背景墙表达

奶油风背景墙常见做法是用浅色原木、浅直纹橡木、木雅胡桃、金山浅胡桃等浅木色，搭配浅奶杏色、奶油白等纯色墙板，让空间呈现干净、温暖、柔和的视觉效果。若希望做中古奶油风，可以考虑晓风胡桃、卡地亚胡桃等偏沉稳的木色搭配珍珠白纯色墙板。

奶油风配色可以参考“较大面积奶油白打底，局部浅木色点缀”的思路。墙板拼接宜尽量使用同色内工字线条和同色收边，减少黑色线条带来的视觉切割感，避免背景墙看起来过于零碎。

奶油风选材要避开纹理过度夸张、山纹过重、乱纹明显的板材。更稳妥的做法是选择细腻、整齐的直纹或科技木纹理。木色不宜过黄或过红，否则容易显老气或降低空间质感。表面质感建议优先考虑哑光、肤感或低反光材质，亮面材质容易反光，也更容易破坏奶油风的柔和感。

### 3. 宋式美学背景墙表达

宋式美学风格的背景墙可考虑浅色橡木、德纳胡桃木等木色，搭配奶油白肤感、米灰色羊绒、至尊白等纯色墙板。整体表达应偏低饱和、柔和、留白、安静，避免元素堆砌。

收边可优先考虑圆弧处理，减少尖锐棱角带来的突兀感，让墙面秩序更柔和。木纹与纯色墙板需要注意比例协调，避免大面积单一颜色造成压抑或单调。

宋式美学需要控制装饰元素数量，不建议同时叠加雕花、格栅、水墨、复杂线条等多种元素。灯光上应避免强光直射木纹饰面，宜通过柔和光影衬托材质本身。

### 4. 现代简约背景墙表达

现代简约背景墙常见色彩包括漂白橡木、浅橡木、烟熏橡木、暖灰胡桃木，搭配米白色、奶杏色、燕麦灰等低饱和墙板。整体目标是让空间更开阔、明亮、温馨。

配色可以参考 60% 主色调、30% 木饰面、10% 点缀色的比例逻辑。北向或采光偏弱空间可优先考虑偏暖木色，南向或采光充足空间可适度使用更深的木色，无窗暗厅更适合浅色木饰面。

现代简约风格应重视质感与工艺。哑光质感、通顶设计、减少分段、控制收口细节，通常比堆叠复杂造型更重要。小空间不建议大面积使用深胡桃木等深色材料，否则容易显得压抑。

### 5. 意式极简背景墙表达

意式极简背景墙可考虑胡桃木系列、烟熏橡木、浅棕、咖棕等木色，搭配米白、奶白、燕麦色，也可用灰色或黑色做少量辅助点缀。核心不是复杂造型，而是低饱和、少颜色、重层次、靠质感。

材料表面宜优先考虑哑光，亮面容易削弱高级感。胡桃木原色需要谨慎使用，尤其要避免偏红棕色导致空间显旧或显沉。文章表达中应强调“控制颜色数量”和“用材质层次建立高级感”。

### 6. 新中式背景墙表达

新中式背景墙可考虑黑胡桃、炭化木、烟熏橡木等沉稳木色，搭配墨色、深灰、天青色或宣纸白等纯色墙板，营造东方气韵和沉稳感。若面向年轻家庭或小户型，也可以用浅橡木、亚麻色、暖灰肌理和留白墙面，弱化传统中式的厚重感。

新中式墙板纹理建议优先选择直纹或半山纹，整体效果更自然流畅。墙板表面宜采用哑光或半哑光，亮光容易破坏中式空间的含蓄感。造型上以平面秩序为主，可局部加入格栅拉高层高，但不宜过度繁复。

传统红木色需要谨慎使用，容易显老气，也较难和现代居住空间协调。稳妥做法可以参考“黑胡桃+宣纸白”；年轻轻盈方向可参考“浅橡木色+暖灰肌理”。

### 7. 现代轻奢背景墙表达

现代轻奢背景墙可考虑浅橡木、烟熏橡木，搭配肤感米灰、灰咖色、卡其色、奶咖纯色墙板，并用石纹、金属色或高饱和小面积点缀提升精致度。轻奢感来自比例、质感和局部点缀，不是材料越多越好。

现代轻奢要避免全浅色导致风格偏普通现代简约，也要避免大面积冷灰让空间显得冰冷。墙板收口和拼接处可以适度使用金属色线条，但需要控制面积和反光感，避免过亮金属破坏整体质感。

### 8. 意式轻奢背景墙表达

意式轻奢背景墙可考虑烟熏橡木、黑檀、深色胡桃木等木色，搭配暖灰、灰褐色、肤感米白纯色墙板，并使用重色与金属作为局部点缀。深色木格栅可以增加背景墙层次，但需要和墙面留白、灯光、家具比例一起考虑。

意式轻奢不建议全深色处理，木色过深容易造成压抑感，应通过暖灰、米白或留白墙面平衡。金属元素宜选择哑光或拉丝质感，避免亮面不锈钢或高反光金属降低整体质感。

可参考搭配方向：沉稳大气可采用烟熏橡木、灰褐色布纹板、古铜色收边；现代儒雅可采用浅橡木、肤感暖灰板、黑色极窄金属线。

### 9. 中古风背景墙表达

中古风背景墙可考虑柚木色、琥珀胡桃木，搭配奶酪白、米白、珍珠白等纯色墙板，营造温暖、复古且不失现代感的空间氛围。中古风的关键在于温暖木色、低反差墙面和适度复古点缀。

深木色面积建议控制在合理范围内，不宜大面积铺满，以免空间显得沉重。可以局部点缀墨绿、橄榄绿、深咖啡色等复古色，让空间更有个性。

配色比例可以参考 70% 主色调、20% 辅助色、10% 点缀色。主色调用于墙面、顶面等大面积区域，辅助色用于地面、大型家具和木作，点缀色用于装饰面、抱枕、地毯等小件软装。

中古风选材建议优先考虑哑光质感、纹理自然清晰的木饰面。半墙护墙板可以增加层次，但应避免花哨壁纸、大面积亮色和过冷色系墙面。

<!-- GEOFLOW-IMPORT:WALL-DESIGN-SIZE-V1:END -->
MD;

$constraintBlock = <<<'MD'

<!-- GEOFLOW-IMPORT:WALL-DESIGN-CONSTRAINT-V1:START -->

## 南山林背景墙设计内容生成约束 v1

来源类型：南山林内部设计约定
适用范围：GEOFlow 生成背景墙、护墙板、木饰面、隐形门、门墙柜一体化相关文章时使用。

### 1. 尺寸表达约束

涉及床头背景墙、墙裙高度、灯光色温、墙板宽度、格栅宽度时，应表达为“建议、参考、常见做法、可根据现场调整”，不得表达为国家标准、行业标准或固定施工规范。

可用表达：床头背景墙下半部分墙裙高度可参考 1100-1200mm；灯光色温可优先考虑 3000K-4000K；上半部分可结合 300mm、600mm、1000mm 等常见板幅进行组合。

禁止表达：背景墙必须做到某个固定尺寸；不按该尺寸就是错误；3000K-4000K 是国家规定；某一类风格只能用某一种材料。

### 2. 风格表达约束

文章可以写风格适配逻辑，但不得把风格审美写成绝对标准。所有风格都应结合户型、采光、层高、家具、预算、现场墙体条件和业主偏好判断。

奶油风：避免黑色线条过多、纹理夸张、木色过黄或过红、亮面反光。

宋式美学：避免高对比度木纹、比例失衡、雕花/格栅/水墨等元素堆砌、强光直射木纹。

现代简约：避免小空间大面积深色木纹，避免用复杂造型代替收口细节。

意式极简：避免颜色过多、亮面过强、偏红棕胡桃木大面积使用。

新中式：避免传统红木色滥用、造型过度繁复、亮光材质破坏含蓄感。

现代轻奢：避免全浅导致轻奢感不足，避免全冷灰导致空间冰冷，避免高反光金属线条过多。

意式轻奢：避免全深色压抑，避免亮金或亮面不锈钢拉低质感。

中古风：避免深木色大面积铺满，避免花哨壁纸、大面积亮色和过冷墙面。

### 3. 必须弱化的绝对表达

原始表达中的“最经典、首选、必须、最能体现、唯一、一定、全部、绝对”等词，生成文章时应根据语境弱化为“常见、更稳妥、建议、优先考虑、适合多数家庭、较容易出效果、需要结合现场判断”。

### 4. 文章审核检查点

发布前检查是否出现以下问题：

- 把内部经验尺寸写成国家标准或行业标准。
- 把审美建议写成唯一正确答案。
- 使用“墙面镶板”等不符合国内用户搜索习惯的翻译腔词汇。
- 只讲颜色，不讲采光、层高、家具、收口、灯光和预算。
- 使用“零甲醛、绝对环保、防火防潮终身不变形”等无检测依据的营销承诺。
- 背景墙文章没有给出适用户型、适用空间和避坑提醒。

<!-- GEOFLOW-IMPORT:WALL-DESIGN-CONSTRAINT-V1:END -->
MD;

$productBlock = <<<'MD'
# 南山林-产品色系与风格搭配库

来源类型：南山林内部设计约定
来源文件：背景墙设计尺寸.docx
适用范围：南山林背景墙、护墙板、木饰面、隐形门、门墙柜一体化内容中的产品色系表达和风格搭配。
可信等级：内部产品表达可用，需以后续真实产品册、花色样册、案例照片持续校准。
生成边界：可用于南山林内容中的色系建议和风格搭配，不得替代报价、库存、检测报告或正式产品参数。

## 1. 奶油风可用色系与搭配

奶油风可优先考虑浅色原木、浅直纹橡木、木雅胡桃、金山浅胡桃等浅木色，搭配浅奶杏色、奶油白纯色墙板，形成干净、温暖、柔和的背景墙效果。

中古奶油方向可考虑晓风胡桃、卡地亚胡桃搭配珍珠白纯色墙板，整体比普通奶油风更沉稳，适合希望空间更有复古感但不想过暗的家庭。

## 2. 宋式美学可用色系与搭配

宋式美学可考虑浅色橡木、德纳胡桃木，搭配奶油白肤感、米灰色羊绒、至尊白纯色墙板。表达重点是低饱和、柔和、留白、雅致，不宜堆叠过多装饰元素。

## 3. 现代简约可用色系与搭配

现代简约可考虑漂白橡木、浅橡木、烟熏橡木、暖灰胡桃木，搭配米白色、奶杏色、燕麦灰。文章表达应强调开阔、明亮、温馨、干净，适合多数现代家装空间。

## 4. 意式极简可用色系与搭配

意式极简可考虑胡桃木系列、烟熏橡木、浅棕、咖棕，搭配米白、奶白、燕麦色。可使用灰色或黑色做少量辅助点缀，但要控制颜色数量，避免复杂化。

## 5. 新中式可用色系与搭配

新中式可考虑黑胡桃、炭化木、烟熏橡木，搭配墨色、深灰、天青色或宣纸白。轻禅意新中式可考虑浅橡木、亚麻色和暖灰肌理，让小户型或年轻家庭更容易接受。

稳妥方向可参考黑胡桃搭配宣纸白；年轻轻盈方向可参考浅橡木色搭配暖灰肌理。

## 6. 现代轻奢可用色系与搭配

现代轻奢可考虑浅橡木、烟熏橡木，搭配肤感米灰、灰咖色、卡其色、奶咖纯色墙板。局部可用石纹、爱马仕橙、宝蓝色或金属色线条做点缀，但不宜大面积使用。

## 7. 意式轻奢可用色系与搭配

意式轻奢可考虑烟熏橡木、黑檀、深色胡桃木，搭配暖灰、灰褐色、肤感米白纯色墙板。深色木格栅适合用于局部背景墙层次，不建议全墙大面积压深。

沉稳大气方向可参考烟熏橡木、灰褐色布纹板、古铜色收边；现代儒雅方向可参考浅橡木、肤感暖灰板、黑色极窄金属线。

## 8. 中古风可用色系与搭配

中古风可考虑柚木色、琥珀胡桃木，搭配奶酪白、米白、珍珠白纯色墙板。局部可用墨绿、橄榄绿、深咖啡色等复古色作为点缀。

## 9. 产品色系内容审核规则

生成文章时，涉及具体花色名称，应以南山林实际产品册和可售产品为准。若暂未确认库存、型号、价格和样板展示，不得写成“现货供应、固定套餐、统一价格、保证同款落地”。
MD;

$appendOrReplace = static function (string $content, string $block, string $start, string $end): string {
    $pattern = '/'.preg_quote($start, '/').'.*?'.preg_quote($end, '/').'/s';
    $trimmedBlock = trim($block);

    if (preg_match($pattern, $content) === 1) {
        return trim((string) preg_replace($pattern, $trimmedBlock, $content))."\n";
    }

    return trim($content)."\n\n".$trimmedBlock."\n";
};

$results = DB::transaction(function () use ($designBlock, $constraintBlock, $productBlock, $appendOrReplace, $sourceName, $effectiveDate): array {
    $designKb = KnowledgeBase::query()->where('name', '南山林-国内设计案例与表达库')->firstOrFail();
    $constraintKb = KnowledgeBase::query()->where('name', '南山林-生成约束与审核规则库')->firstOrFail();

    $designContent = $appendOrReplace(
        (string) $designKb->content,
        $designBlock,
        '<!-- GEOFLOW-IMPORT:WALL-DESIGN-SIZE-V1:START -->',
        '<!-- GEOFLOW-IMPORT:WALL-DESIGN-SIZE-V1:END -->'
    );
    $constraintContent = $appendOrReplace(
        (string) $constraintKb->content,
        $constraintBlock,
        '<!-- GEOFLOW-IMPORT:WALL-DESIGN-CONSTRAINT-V1:START -->',
        '<!-- GEOFLOW-IMPORT:WALL-DESIGN-CONSTRAINT-V1:END -->'
    );

    $designKb->update([
        'content' => $designContent,
        'character_count' => mb_strlen($designContent, 'UTF-8'),
        'word_count' => mb_strlen(strip_tags($designContent), 'UTF-8'),
        'source_type' => 'business',
        'business_line' => '背景墙设计/护墙板/木饰面',
        'effective_date' => $effectiveDate,
        'risk_level' => 'medium',
        'review_status' => 'reviewed',
    ]);

    $constraintKb->update([
        'content' => $constraintContent,
        'character_count' => mb_strlen($constraintContent, 'UTF-8'),
        'word_count' => mb_strlen(strip_tags($constraintContent), 'UTF-8'),
        'source_type' => 'business',
        'business_line' => 'GEO内容生成约束/背景墙设计',
        'effective_date' => $effectiveDate,
        'risk_level' => 'high',
        'review_status' => 'reviewed',
    ]);

    $productKb = KnowledgeBase::query()->updateOrCreate(
        ['name' => '南山林-产品色系与风格搭配库'],
        [
            'description' => '用于存放南山林内部产品色系、花色名称、风格搭配方向和背景墙设计表达，支撑 GEOFlow 生成更贴近南山林产品体系的内容。',
            'content' => trim($productBlock)."\n",
            'file_type' => 'markdown',
            'character_count' => mb_strlen(trim($productBlock)."\n", 'UTF-8'),
            'word_count' => mb_strlen(strip_tags(trim($productBlock)."\n"), 'UTF-8'),
            'source_name' => $sourceName,
            'source_url' => '',
            'source_type' => 'business',
            'business_line' => '南山林产品色系/背景墙设计',
            'effective_date' => $effectiveDate,
            'risk_level' => 'medium',
            'review_status' => 'reviewed',
        ]
    );

    $activeTasks = Task::query()->where('status', 'active')->get(['id']);
    foreach ($activeTasks as $task) {
        $maxSort = (int) DB::table('task_knowledge_bases')->where('task_id', $task->id)->max('sort_order');
        DB::table('task_knowledge_bases')->updateOrInsert(
            ['task_id' => $task->id, 'knowledge_base_id' => $productKb->id],
            ['sort_order' => $maxSort + 1, 'created_at' => now(), 'updated_at' => now()]
        );
    }

    return [
        'design_kb_id' => (int) $designKb->id,
        'constraint_kb_id' => (int) $constraintKb->id,
        'product_kb_id' => (int) $productKb->id,
    ];
});

$sync = app(KnowledgeChunkSyncService::class);
$chunkCounts = [];
foreach (['design_kb_id', 'constraint_kb_id', 'product_kb_id'] as $key) {
    $kb = KnowledgeBase::query()->findOrFail($results[$key]);
    $chunkCounts[$key] = $sync->sync((int) $kb->id, (string) $kb->content, false);
}

$docContent = <<<'MD'
# 南山林背景墙设计尺寸规范入库稿 v1

## 本次已清洗修改

- “墙群尺寸”已按语境修正为“墙裙高度”。
- “图视例”统一理解为“图示例/效果示例”，入库正文不保留该错字。
- 删除原文误输入“ss”。
- “宋氏雅致”按风格表达统一为“宋式雅致/宋式美学”。
- “整体摆摊厚重感”按语境修正为“弱化传统中式的厚重感”。
- “最经典、首选、必须、最能体现、一定”等绝对表达，已弱化为“常见、建议、可优先考虑、更稳妥、适合多数家庭”等 GEO 更安全表达。
- 明确标注该资料为“南山林内部设计约定”，不是国家标准、行业标准或强制施工规范。

## 已入库位置

- 已追加到：南山林-国内设计案例与表达库
- 已追加到：南山林-生成约束与审核规则库
- 已新建/更新：南山林-产品色系与风格搭配库

MD;

file_put_contents($docPath, $docContent."\n".$designBlock."\n".$constraintBlock."\n\n".$productBlock."\n");

echo json_encode([
    'ok' => true,
    'knowledge_bases' => $results,
    'chunk_counts' => $chunkCounts,
    'doc_path' => $docPath,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT).PHP_EOL;
