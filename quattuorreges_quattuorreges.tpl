{OVERALL_GAME_HEADER}
<div id="qtr-content">
    <div id="qtr-board-container">
        <div class="qtr-captures" data-color="black"></div>
        <div id="qtr-board">
            <div id="qtr-board-background"></div>
            <svg id="qtr-selection-svg" viewBox="0 0 714 630" xmlns="http://www.w3.org/2000/svg">
                <defs>
                    <pattern id="qtr-selection-fill-pattern" width="16" height="16"
                             patternUnits="userSpaceOnUse" patternTransform="rotate(45)">
                        <line x2="5"/>
                    </pattern>
                </defs>
                <path id="qtr-selection-svg-path" stroke-linecap="round" d=""/>
            </svg>
            <div id="qtr-board-rows">
                <!-- BEGIN row -->
                <div class="qtr-board-row">
                    <!-- BEGIN space -->
                    <div id="qtr-board-space-{X}-{Y}" class="qtr-board-space" data-x="{X}" data-y="{Y}" data-color="{COLOR}"></div>
                    <!-- END space -->
                </div>
                <!-- END row -->
            </div>
        </div>
        <div class="qtr-captures" data-color="red"></div>
    </div>
</div>

{OVERALL_GAME_FOOTER}
